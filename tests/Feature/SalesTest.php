<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\JournalEntryLine;
use App\Models\SalesIncome;
use App\Services\Approvals\ApprovalService;
use App\Services\Sales\SalesIncomeService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Collection;

class SalesTest extends FinanceTestCase
{
    public function test_super_admin_sales_income_posts_cash_and_product_sales(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);
        $cash = $this->cashAccount();
        $before = (string) $cash->current_balance;

        $this->actingAs($super)
            ->post(route('sales.store'), [
                'source' => 'website',
                'amount' => '30000.00',
                'transaction_date' => '2026-06-02',
                'payment_method' => 'cash',
                'financial_account_id' => $cash->id,
                'note' => 'Website takings.',
            ])
            ->assertRedirect();

        $income = SalesIncome::query()->firstOrFail();
        $this->assertSame(DocumentStatus::Approved, $income->status);
        $this->assertNotNull($income->journal_entry_id);
        $this->assertSame(Money::of($before)->add('30000.00')->amount(), (string) $cash->fresh()->current_balance);

        $income->load('journalEntry.lines.account');
        $this->assertJournalBalances($income);
        $lines = $income->journalEntry->lines;
        $this->assertSame('30000.00', $this->side($lines, ChartAccountCode::Cash, 'debit'));
        $this->assertSame('30000.00', $this->side($lines, ChartAccountCode::ProductSales, 'credit'));
    }

    public function test_sales_manager_income_stays_pending_until_approved(): void
    {
        $sales = $this->userWithRole(RoleName::SalesManager);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $cash = $this->cashAccount();

        $income = app(SalesIncomeService::class)->create($sales, [
            'source' => 'shop',
            'amount' => '1500.00',
            'transaction_date' => '2026-06-03',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ]);

        $this->assertSame(DocumentStatus::Pending, $income->status);
        $this->assertNull($income->journal_entry_id);

        app(ApprovalService::class)->approve($income->approvalRequest, $accountant, 'Counted.');

        $income->refresh();
        $this->assertSame(DocumentStatus::Approved, $income->status);
        $this->assertNotNull($income->journal_entry_id);
    }

    public function test_sales_permissions_gate_the_screens(): void
    {
        $sales = $this->userWithRole(RoleName::SalesManager);
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partner = $this->userWithRole(RoleName::Partner);
        $payload = [
            'source' => 'website',
            'amount' => '100.00',
            'transaction_date' => '2026-06-02',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
        ];

        $this->actingAs($viewer)->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Sales/Index'));

        $this->actingAs($viewer)->post(route('sales.store'), $payload)->assertForbidden();
        $this->actingAs($partner)->get(route('sales.index'))->assertForbidden();

        $this->actingAs($sales)
            ->post(route('sales.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('sales_incomes', ['amount' => '100.00']);
    }

    public function test_api_sales_create_records_lump_sum_income(): void
    {
        $sales = $this->userWithRole(RoleName::SalesManager);
        $token = $sales->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/sales', [
                'source' => 'shop',
                'amount' => '250.00',
                'transaction_date' => '2026-06-04',
                'payment_method' => 'cash',
                'financial_account_id' => $this->cashAccount()->id,
            ])
            ->assertCreated()
            ->assertJsonPath('status', DocumentStatus::Pending->value);

        $this->assertDatabaseHas('sales_incomes', ['amount' => '250.00', 'source' => 'shop']);
    }

    private function assertJournalBalances(SalesIncome $income): void
    {
        $debits = '0.00';
        $credits = '0.00';

        foreach ($income->journalEntry->lines as $line) {
            $debits = Money::of($debits)->add((string) $line->debit)->amount();
            $credits = Money::of($credits)->add((string) $line->credit)->amount();
        }

        $this->assertSame($debits, $credits);
        $this->assertNotSame('0.00', $debits);
    }

    /**
     * @param  Collection<int, JournalEntryLine>  $lines
     */
    private function side(Collection $lines, string $code, string $column): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            if ($line->account?->code !== $code) {
                continue;
            }

            $total = Money::of($total)->add((string) $line->{$column})->amount();
        }

        return $total;
    }
}
