<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PartnerStatus;
use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\Finance\PartnerProfileService;
use App\Support\Money;
use Database\Seeders\DemoPartnershipSeeder;
use ZipArchive;

class PartnerProfileTest extends FinanceTestCase
{
    public function test_profile_totals_reconcile_with_the_capital_ledger(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create([
            'investment_percentage' => '100.0000',
            'ownership_percentage' => '100.0000',
        ]);

        $this->invest($admin, $accountant, $partner, '400.00', '2026-01-15');
        $this->invest($admin, $accountant, $partner, '100.00', '2026-03-15');

        $this->actingAs($admin)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '50.00', [
                'transaction_date' => '2026-04-01',
            ]))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $this->actingAs($accountant)
            ->post(route('approvals.approve', $withdrawal->approvalRequest))
            ->assertRedirect();

        $profiles = app(PartnerProfileService::class);
        $summary = $profiles->summary($partner);
        $history = $profiles->history($partner, null, null);
        $movement = Money::of($history['opening']['amount'])
            ->add($history['credit_total']['amount'])
            ->sub($history['debit_total']['amount'])
            ->amount();

        $this->assertSame('500.00', $summary['gross_investment']['amount']);
        $this->assertSame('50.00', $summary['withdrawals']['amount']);
        $this->assertSame('0.00', $summary['allocated_profit']['amount']);
        $this->assertSame('450.00', $summary['net_capital']['amount']);
        $this->assertSame('0.00', $history['opening']['amount']);
        $this->assertSame('450.00', $history['closing']['amount']);
        $this->assertSame($history['closing']['amount'], $movement);
        $this->assertSame('450.00', $history['lines'][array_key_last($history['lines'])]['running_balance']);
        $this->assertSame('Investment', $history['lines'][0]['type']);
        $this->assertSame('Withdrawal', $history['lines'][2]['type']);
        $this->assertStringContainsString('৳', $summary['net_capital']['formatted']);

        $ranged = $profiles->history($partner, '2026-03-01', '2026-03-31');
        $rangedMovement = Money::of($ranged['opening']['amount'])
            ->add($ranged['credit_total']['amount'])
            ->sub($ranged['debit_total']['amount'])
            ->amount();

        $this->assertSame('400.00', $ranged['opening']['amount']);
        $this->assertSame('100.00', $ranged['credit_total']['amount']);
        $this->assertSame('0.00', $ranged['debit_total']['amount']);
        $this->assertSame('500.00', $ranged['closing']['amount']);
        $this->assertSame($ranged['closing']['amount'], $rangedMovement);
        $this->assertCount(1, $ranged['lines']);

        $this->actingAs($admin)
            ->get(route('partners.profile', $partner))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Partners/Profile')
                ->where('summary.net_capital.amount', '450.00')
                ->where('summary.gross_investment.amount', '500.00')
                ->where('totals.closing.amount', '450.00')
                ->where('history.total', 3)
                ->where('history.data.2.running_balance', '450.00')
                ->where('summary.investment_basis_label', PartnerProfileService::INVESTMENT_BASIS)
                ->where('summary.capital_basis_label', PartnerProfileService::CAPITAL_BASIS));

        $this->actingAs($admin)
            ->get(route('partners.profile', ['partner' => $partner, 'from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totals.opening.amount', '400.00')
                ->where('totals.closing.amount', '500.00')
                ->where('history.total', 1));

        $excel = $this->actingAs($admin)->get(route('partners.profile.excel', $partner));
        $excel->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents((string) $path, $excel->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open((string) $path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('450.00', $sheet);
        $this->assertStringContainsString('500.00', $sheet);
        $zip->close();

        $pdf = $this->actingAs($admin)->get(route('partners.profile.pdf', $partner));
        $pdf->assertOk();
        $text = $this->pdfText($pdf->getContent());
        $this->assertStringContainsString('450.00', $text);
        $this->assertStringContainsString('500.00', $text);
        $this->assertStringContainsString('Promotion contribution', $text);
        $this->assertStringContainsString('Net capital', $text);
        $this->assertStringContainsString('Date range: all dates', $text);
        $this->assertStringContainsString(PartnerProfileService::INVESTMENT_BASIS, $text);
        $this->assertStringContainsString(PartnerProfileService::CAPITAL_BASIS, $text);
        $this->assertStringContainsString(' · ', $text);
        $this->assertStringNotContainsString('?', $text);

        $filtered = $this->actingAs($admin)->get(route('partners.profile.pdf', [
            'partner' => $partner,
            'from' => '2026-03-01',
            'to' => '2026-03-31',
        ]));
        $filteredText = $this->pdfText($filtered->getContent());
        $this->assertStringContainsString('Date range: 01-Mar-2026 to 31-Mar-2026', $filteredText);
        $this->assertStringContainsString('Balance 500.00', $filteredText);
        $this->assertStringNotContainsString('Balance 450.00', $filteredText);
    }

    public function test_investment_and_capital_shares_sum_to_100_percent(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $inactive = Partner::factory()->inactive()->create([
            'investment_percentage' => '25.0000',
            'ownership_percentage' => '0.0000',
        ]);
        $first = Partner::factory()->create([
            'investment_percentage' => '20.0000',
            'ownership_percentage' => '20.0000',
            'status' => PartnerStatus::Active,
        ]);
        $second = Partner::factory()->create([
            'investment_percentage' => '30.0000',
            'ownership_percentage' => '30.0000',
            'status' => PartnerStatus::Active,
        ]);
        $third = Partner::factory()->create([
            'investment_percentage' => '50.0000',
            'ownership_percentage' => '50.0000',
            'status' => PartnerStatus::Active,
        ]);

        $this->invest($admin, $accountant, $first, '100.00', '2026-02-01');
        $this->invest($admin, $accountant, $second, '200.00', '2026-02-01');
        $this->invest($admin, $accountant, $third, '700.00', '2026-02-01');

        $profiles = app(PartnerProfileService::class);
        $investment = '0.0000';
        $capital = '0.0000';

        foreach (Partner::query()->orderBy('id')->get() as $partner) {
            $summary = $profiles->summary($partner);
            $investment = bcadd($investment, $summary['investment_share'], 4);
            $capital = bcadd($capital, $summary['capital_share'], 4);
        }

        $this->assertSame('100.0000', $investment);
        $this->assertSame('100.0000', $capital);
        $this->assertSame('0.0000', $profiles->summary($inactive)['investment_share']);
        $this->assertSame('20.0000', $profiles->summary($first)['investment_share']);
        $this->assertSame('30.0000', $profiles->summary($second)['investment_share']);
        $this->assertSame('50.0000', $profiles->summary($third)['investment_share']);
        $this->assertSame('10.0000', $profiles->summary($first)['capital_share']);
        $this->assertSame('20.0000', $profiles->summary($second)['capital_share']);
        $this->assertSame('70.0000', $profiles->summary($third)['capital_share']);
        $this->assertFalse($profiles->summary($inactive)['in_investment_basis']);
        $this->assertTrue($profiles->summary($first)['in_investment_basis']);
    }

    public function test_history_is_paginated(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create(['investment_percentage' => '100.0000']);

        for ($day = 1; $day <= 16; $day++) {
            $this->invest($admin, $accountant, $partner, '1.00', sprintf('2026-01-%02d', $day));
        }

        $this->actingAs($admin)
            ->get(route('partners.profile', $partner))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('history.per_page', 15)
                ->where('history.total', 16)
                ->where('history.last_page', 2)
                ->has('history.data', 15));

        $this->actingAs($admin)
            ->get(route('partners.profile', ['partner' => $partner, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('history.current_page', 2)
                ->has('history.data', 1)
                ->where('history.data.0.running_balance', '16.00')
                ->where('totals.closing.amount', '16.00'));
    }

    public function test_a_partner_cannot_view_another_partners_profile(): void
    {
        [$firstUser, $first] = $this->linkedPartner('Rahim Uddin');
        [, $second] = $this->linkedPartner('Fatema Akter');
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $sales = $this->userWithRole(RoleName::SalesManager);

        $this->actingAs($firstUser)->get(route('partners.profile', $second))->assertForbidden();
        $this->actingAs($firstUser)->get(route('partners.profile.excel', $second))->assertForbidden();
        $this->actingAs($firstUser)->get(route('partners.profile.pdf', $second))->assertForbidden();

        $this->actingAs($firstUser)
            ->get(route('partners.profile', $first))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('partner.id', $first->id));

        $this->actingAs($admin)->get(route('partners.profile', $second))->assertOk();
        $this->actingAs($accountant)->get(route('partners.profile', $second))->assertOk();
        $this->actingAs($sales)->get(route('partners.profile', $first))->assertForbidden();
    }

    public function test_seeded_profile_matches_the_ledger_and_shares_sum_to_100_percent(): void
    {
        $this->seed(DemoPartnershipSeeder::class);

        $rahim = Partner::query()->where('partner_code', 'P-0001')->firstOrFail();
        $fatema = Partner::query()->where('partner_code', 'P-0002')->firstOrFail();
        $profiles = app(PartnerProfileService::class);
        $summary = $profiles->summary($rahim);
        $history = $profiles->history($rahim, null, null);
        $movement = Money::of($history['opening']['amount'])
            ->add($history['credit_total']['amount'])
            ->sub($history['debit_total']['amount'])
            ->amount();

        $this->assertSame('7180.00', $summary['net_capital']['amount']);
        $this->assertSame('180.00', $summary['allocated_profit']['amount']);
        $this->assertSame('7180.00', $history['closing']['amount']);
        $this->assertSame($history['closing']['amount'], $movement);

        $investment = '0.0000';
        $capital = '0.0000';

        foreach (Partner::query()->orderBy('id')->get() as $partner) {
            $row = $profiles->summary($partner);
            $investment = bcadd($investment, $row['investment_share'], 4);
            $capital = bcadd($capital, $row['capital_share'], 4);
        }

        $this->assertSame('100.0000', $investment);
        $this->assertSame('100.0000', $capital);

        $rahimUser = User::query()->where('email', 'rahim.uddin@mpstore.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();

        $this->actingAs($rahimUser)->get(route('partners.profile', $fatema))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('partners.profile', $rahim))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.net_capital.amount', '7180.00')
                ->where('summary.allocated_profit.amount', '180.00')
                ->where('totals.closing.amount', '7180.00'));

        $pdf = $this->pdfText($this->actingAs($admin)->get(route('partners.profile.pdf', $rahim))->getContent());
        $this->assertStringContainsString('Investment INV-00001 · Rahim Uddin', $pdf);
        $this->assertStringContainsString('Profit allocation PAL-00001 · Rahim Uddin', $pdf);
        $this->assertStringNotContainsString('•', $pdf);
        $this->assertStringContainsString('Promotion contribution', $pdf);
        $this->assertStringContainsString('৳2,000.00', $pdf);
        $this->assertStringContainsString('Net capital', $pdf);
        $this->assertStringContainsString('৳7,180.00', $pdf);
        $this->assertStringContainsString('Balance 7180.00', $pdf);
        $this->assertStringContainsString(PartnerProfileService::INVESTMENT_BASIS, $pdf);
        $this->assertStringContainsString(PartnerProfileService::CAPITAL_BASIS, $pdf);
        $this->assertStringNotContainsString('?', $pdf);

        $april = $this->pdfText($this->actingAs($admin)->get(route('partners.profile.pdf', [
            'partner' => $rahim,
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]))->getContent());
        $this->assertStringContainsString('Date range: 01-Apr-2026 to 30-Apr-2026', $april);
        $this->assertStringContainsString('Promotion contribution', $april);
        $this->assertStringContainsString('Net capital', $april);
        $this->assertStringNotContainsString('INV-00001', $april);
    }

    private function pdfText(string $pdf): string
    {
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica /Encoding /WinAnsiEncoding', $pdf);

        $viewer = $this->viewerText($pdf);

        if ($viewer !== null) {
            $this->assertStringNotContainsString('•', $viewer);

            return $viewer;
        }

        return $this->contentStreamText($pdf);
    }

    /**
     * Text a viewer extracts. Byte 0xB7 is a bullet under StandardEncoding and a middle dot under WinAnsiEncoding.
     */
    private function viewerText(string $pdf): ?string
    {
        $checked = shell_exec('command -v pdftotext');
        $binary = is_string($checked) ? trim($checked) : '';

        if ($binary === '' || ! is_executable($binary)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'profile-pdf-');
        $this->assertNotFalse($path);
        file_put_contents($path, $pdf);

        $pipes = [];
        $process = proc_open(
            [$binary, '-enc', 'UTF-8', '-layout', $path, '-'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($path);

        $this->assertSame(0, $code, 'pdftotext failed: '.$error);
        $this->assertIsString($output);

        $flat = preg_replace('/\s+/u', ' ', $output) ?? $output;

        return trim($flat);
    }

    private function contentStreamText(string $pdf): string
    {
        $winAnsi = str_contains($pdf, '/BaseFont /Helvetica /Encoding /WinAnsiEncoding');
        $text = '';

        preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj|<([0-9A-Fa-f]+)>\s*Tj/s', $pdf, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (str_starts_with($match[0], '(')) {
                $literal = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $match[1]);
                $text .= $winAnsi
                    ? mb_convert_encoding($literal, 'UTF-8', 'Windows-1252')
                    : str_replace("\xB7", '•', $literal);

                continue;
            }

            $hex = $match[2];
            for ($index = 0; $index < strlen($hex); $index += 4) {
                $text .= mb_chr((int) hexdec(substr($hex, $index, 4)));
            }
        }

        return $text;
    }

    private function invest(User $actor, User $approver, Partner $partner, string $amount, string $date): void
    {
        $this->actingAs($actor)->post(route('investments.store'), [
            'partner_id' => $partner->id,
            'amount' => $amount,
            'transaction_date' => $date,
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
        ])->assertRedirect();

        $investment = PartnerInvestment::query()->where('partner_id', $partner->id)->latest('id')->firstOrFail();
        $this->actingAs($approver)
            ->post(route('approvals.approve', $investment->approvalRequest))
            ->assertRedirect();
    }
}
