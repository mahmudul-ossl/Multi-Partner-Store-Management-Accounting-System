<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Exceptions\ApprovalStateException;
use App\Exceptions\SelfApprovalException;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\ProfitAllocation;
use App\Models\User;
use App\Services\Accounting\AllocationService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\PeriodGuard;
use App\Services\Accounting\PeriodService;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\PartnerStatementService;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use App\Support\ReportWindow;
use Carbon\Carbon;

class StatementsTest extends FinanceTestCase
{
    public function test_seeded_ledger_statements_balance(): void
    {
        $this->seed();

        $statements = app(FinancialStatementService::class);
        $report = $statements->profitAndLoss('2026-01-01', '2026-12-31');

        $this->assertSame('3180.00', $this->lineAmount($report['revenue'], ChartAccountCode::ProductSales));
        $this->assertSame('2500.00', $this->lineAmount($report['expenses'], ChartAccountCode::PromotionExpense));
        $this->assertSame('800.00', $this->lineAmount($report['expenses'], ChartAccountCode::Rent));
        $this->assertSame('400.00', $this->lineAmount($report['expenses'], ChartAccountCode::Electricity));
        $this->assertSame(
            Money::of($report['total_revenue']['amount'])->sub($report['total_cogs']['amount'])->amount(),
            $report['gross_profit']['amount'],
        );
        $this->assertSame(
            Money::of($report['gross_profit']['amount'])->sub($report['total_expenses']['amount'])->amount(),
            $report['net_profit']['amount'],
        );
        $this->assertSame($report['total_revenue']['amount'], $this->independentNet(AccountType::Income, '2026-01-01', '2026-12-31', true));

        $sheet = $statements->balanceSheet('2026-12-31');
        $right = Money::of($sheet['liabilities']['total']['amount'])->add($sheet['equity']['total']['amount'])->amount();
        $this->assertTrue($sheet['balanced']);
        $this->assertSame($sheet['assets']['total']['amount'], $right);
        $this->assertSame($sheet['liabilities_and_equity']['amount'], $right);

        $trial = $statements->trialBalance('2026-12-31');
        $this->assertTrue($trial['balanced']);
        $this->assertSame($trial['debit_total']['amount'], $trial['credit_total']['amount']);
        $this->assertNotSame('0.00', $trial['debit_total']['amount']);

        $rahim = Partner::query()->where('partner_code', 'P-0001')->firstOrFail();
        $position = app(PartnerStatementService::class)->position($rahim);
        $this->assertSame('180.00', $position['profit_share']['amount']);
        $this->assertSame('7180.00', $position['current_capital']['amount']);
        $this->assertTrue(collect(app(PartnerStatementService::class)->statement($rahim))->contains(
            fn (array $line): bool => $line['credit'] === '180.00',
        ));

        $allocation = ProfitAllocation::query()->with('journalEntry.lines')->firstOrFail();
        $this->assertSame('770.00', (string) $allocation->amount);
        $this->assertSame(DocumentStatus::Approved, $allocation->status);
        $this->assertJournalBalances($allocation->journalEntry);
        $this->assertSame('2026-05-31', app(PeriodGuard::class)->closedThrough());
        $this->assertSame('1150.00', (string) FinancialAccount::query()->where('name', 'Cash')->firstOrFail()->current_balance);

        $viewer = User::query()->where('email', 'viewer@mpstore.test')->firstOrFail();
        $this->actingAs($viewer)
            ->get('/accounting/reports/profit-loss?period=custom&from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/ProfitLoss')
                ->where('report.revenue', fn ($rows): bool => collect($rows)->firstWhere('code', ChartAccountCode::ProductSales)['amount'] === '3180.00')
                ->where('report.total_revenue.formatted', fn (string $formatted): bool => str_contains($formatted, '৳')));

        $this->actingAs($viewer)
            ->get('/accounting/reports/balance-sheet?as_of=2026-12-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/BalanceSheet')->where('report.balanced', true));

        $this->actingAs($viewer)
            ->get('/accounting/reports/trial-balance?as_of=2026-12-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/TrialBalance')
                ->where('report.balanced', true)
                ->where('report.debit_total.amount', $trial['debit_total']['amount'])
                ->where('report.credit_total.amount', $trial['credit_total']['amount']));
    }

    public function test_profit_and_loss_periods_follow_the_ledger_dates(): void
    {
        Carbon::setTestNow('2026-06-15');

        try {
            $admin = $this->userWithRole(RoleName::Admin);
            $this->postIncome($admin, '2026-06-15', '100.00');
            $this->postIncome($admin, '2026-06-16', '40.00');
            $this->postIncome($admin, '2026-05-20', '25.00');
            $this->postExpense($admin, '2026-06-01', ChartAccountCode::Cogs, '30.00');
            $this->postExpense($admin, '2026-06-02', ChartAccountCode::Rent, '10.00');

            $statements = app(FinancialStatementService::class);
            $month = $statements->profitAndLoss('2026-06-01', '2026-06-30');
            [$from, $to] = ReportWindow::resolve('this_month');
            $resolved = $statements->profitAndLoss($from, $to);

            $this->assertSame('2026-06-01', $from);
            $this->assertSame('2026-06-30', $to);
            $this->assertSame('140.00', $resolved['total_revenue']['amount']);
            $this->assertSame('30.00', $resolved['total_cogs']['amount']);
            $this->assertSame('110.00', $resolved['gross_profit']['amount']);
            $this->assertSame('10.00', $resolved['total_expenses']['amount']);
            $this->assertSame('100.00', $resolved['net_profit']['amount']);
            $this->assertSame($month['net_profit']['amount'], $resolved['net_profit']['amount']);

            $previous = $statements->profitAndLoss(...ReportWindow::resolve('previous_month'));
            $this->assertSame('25.00', $previous['total_revenue']['amount']);
            $this->assertSame('25.00', $previous['net_profit']['amount']);

            $today = $statements->profitAndLoss(...ReportWindow::resolve('today'));
            $this->assertSame('100.00', $today['total_revenue']['amount']);

            $week = $statements->profitAndLoss(...ReportWindow::resolve('this_week'));
            $this->assertSame('2026-06-15', ReportWindow::resolve('this_week')[0]);
            $this->assertSame('2026-06-21', ReportWindow::resolve('this_week')[1]);
            $this->assertSame('140.00', $week['total_revenue']['amount']);
            $this->assertSame('0.00', $week['total_cogs']['amount']);
            $this->assertSame('0.00', $week['total_expenses']['amount']);

            $custom = $statements->profitAndLoss(...ReportWindow::resolve('custom', '2026-05-01', '2026-05-31'));
            $this->assertSame('25.00', $custom['total_revenue']['amount']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_allocation_methods_round_and_custom_must_total_100(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $first = Partner::factory()->create([
            'ownership_percentage' => '60.0000',
            'investment_percentage' => '25.0000',
        ]);
        $second = Partner::factory()->create([
            'ownership_percentage' => '40.0000',
            'investment_percentage' => '75.0000',
        ]);
        $third = Partner::factory()->create([
            'ownership_percentage' => '0.0000',
            'investment_percentage' => '0.0000',
        ]);
        $service = app(AllocationService::class);

        $ownership = $service->create($admin, [
            'amount' => '100.00',
            'transaction_date' => '2026-09-01',
            'method' => 'ownership',
        ]);
        $this->assertShares($ownership, [
            $first->id => '60.00',
            $second->id => '40.00',
        ], '100.00');

        $investment = $service->create($admin, [
            'amount' => '10.00',
            'transaction_date' => '2026-09-01',
            'method' => 'investment',
        ]);
        $this->assertShares($investment, [
            $first->id => '2.50',
            $second->id => '7.50',
        ], '10.00');

        $custom = $service->create($admin, [
            'amount' => '100.00',
            'transaction_date' => '2026-09-01',
            'method' => 'custom',
            'lines' => [
                ['partner_id' => $first->id, 'percentage' => '33.3333'],
                ['partner_id' => $second->id, 'percentage' => '33.3333'],
                ['partner_id' => $third->id, 'percentage' => '33.3334'],
            ],
        ]);
        $this->assertShares($custom, [
            $first->id => '33.33',
            $second->id => '33.33',
            $third->id => '33.34',
        ], '100.00');

        try {
            $service->create($admin, [
                'amount' => '100.00',
                'transaction_date' => '2026-09-01',
                'method' => 'custom',
                'lines' => [
                    ['partner_id' => $first->id, 'percentage' => '50'],
                    ['partner_id' => $second->id, 'percentage' => '40'],
                ],
            ]);
            $this->fail('Custom percentages that do not total 100 should be rejected.');
        } catch (ApprovalStateException $exception) {
            $this->assertSame('Custom percentages must total 100%.', $exception->getMessage());
        }
    }

    public function test_self_approval_is_rejected_and_the_statement_shows_the_share(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create(['ownership_percentage' => '100.0000']);
        $allocation = app(AllocationService::class)->create($admin, [
            'amount' => '250.00',
            'transaction_date' => '2026-09-01',
            'method' => 'ownership',
        ]);

        try {
            app(ApprovalService::class)->approve($allocation->approvalRequest, $admin);
            $this->fail('The requester should not approve the allocation.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $allocation->refresh();
        $this->assertSame(DocumentStatus::Pending, $allocation->status);
        $this->assertNull($allocation->journal_entry_id);

        app(ApprovalService::class)->approve($allocation->approvalRequest, $accountant, 'Posted.');
        $allocation->refresh()->load('journalEntry.lines.account', 'lines');

        $this->assertSame(DocumentStatus::Approved, $allocation->status);
        $this->assertJournalBalances($allocation->journalEntry);

        $retained = '0.00';
        $capital = '0.00';

        foreach ($allocation->journalEntry->lines as $line) {
            if ($line->account->code === ChartAccountCode::RetainedEarnings) {
                $retained = Money::of($retained)->add((string) $line->debit)->amount();
            }

            if ($line->account->code === ChartAccountCode::PartnerCapital) {
                $capital = Money::of($capital)->add((string) $line->credit)->amount();
                $this->assertSame($partner->id, $line->partner_id);
            }
        }

        $this->assertSame('250.00', $retained);
        $this->assertSame('250.00', $capital);
        $this->assertSame('250.00', app(PartnerStatementService::class)->position($partner)['profit_share']['amount']);
        $this->assertTrue(collect(app(PartnerStatementService::class)->statement($partner))->contains(
            fn (array $line): bool => $line['credit'] === '250.00',
        ));
    }

    public function test_closed_period_blocks_posting_and_a_later_approval(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        Partner::factory()->create(['ownership_percentage' => '100.0000']);
        app(PeriodService::class)->close($admin, '2026-06-30', 'June close');

        try {
            $this->postIncome($admin, '2026-06-15', '10.00');
            $this->fail('A closed date should reject the journal.');
        } catch (ApprovalStateException $exception) {
            $this->assertSame('That date is in a closed period.', $exception->getMessage());
        }

        try {
            app(AllocationService::class)->create($admin, [
                'amount' => '20.00',
                'transaction_date' => '2026-06-15',
                'method' => 'ownership',
            ]);
            $this->fail('A closed date should reject the allocation.');
        } catch (ApprovalStateException $exception) {
            $this->assertSame('That date is in a closed period.', $exception->getMessage());
        }

        $open = $this->postIncome($admin, '2026-07-01', '10.00');
        $this->assertJournalBalances($open);

        $allocation = app(AllocationService::class)->create($accountant, [
            'amount' => '30.00',
            'transaction_date' => '2026-08-01',
            'method' => 'ownership',
        ]);
        app(PeriodService::class)->close($admin, '2026-08-31', 'August close');
        $journals = JournalEntry::query()->count();

        try {
            app(ApprovalService::class)->approve($allocation->approvalRequest, $admin, 'Too late.');
            $this->fail('Approval should not post into a closed period.');
        } catch (ApprovalStateException $exception) {
            $this->assertSame('That date is in a closed period.', $exception->getMessage());
        }

        $allocation->refresh();
        $this->assertSame(DocumentStatus::Pending, $allocation->status);
        $this->assertNull($allocation->journal_entry_id);
        $this->assertSame($journals, JournalEntry::query()->count());
    }

    public function test_statement_screens_follow_existing_permissions(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $partner = $this->userWithRole(RoleName::Partner);
        $accountant = $this->userWithRole(RoleName::Accountant);
        Partner::factory()->create(['ownership_percentage' => '100.0000']);

        $this->actingAs($viewer)->get('/accounting/reports/profit-loss')->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/ProfitLoss'));
        $this->actingAs($viewer)->get('/accounting/reports/balance-sheet')->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/BalanceSheet'));
        $this->actingAs($viewer)->get('/accounting/reports/trial-balance')->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/TrialBalance'));
        $this->actingAs($inventory)->get('/accounting/reports/profit-loss')->assertForbidden();
        $this->actingAs($partner)->get('/accounting/reports/balance-sheet')->assertForbidden();
        $this->actingAs($partner)->get('/accounting/reports/profit-loss')->assertOk();
        $this->actingAs($viewer)->post('/accounting/allocations', [
            'amount' => '100.00',
            'transaction_date' => '2026-09-01',
            'method' => 'ownership',
        ])->assertForbidden();

        $this->actingAs($accountant)->post('/accounting/allocations', [
            'amount' => '100.00',
            'transaction_date' => '2026-09-01',
            'method' => 'ownership',
            'note' => 'Quarter share',
        ])->assertRedirect()->assertSessionHas('success', 'Profit allocation submitted for approval.');

        $this->assertSame(1, ProfitAllocation::query()->count());
    }

    /**
     * @param  list<array{code: string, amount: string}>  $lines
     */
    private function lineAmount(array $lines, string $code): string
    {
        $row = collect($lines)->firstWhere('code', $code);

        return $row['amount'] ?? '0.00';
    }

    private function independentNet(AccountType $type, string $from, string $to, bool $creditMinusDebit): string
    {
        $lines = JournalEntryLine::query()
            ->whereHas('account', fn ($query) => $query->where('type', $type->value))
            ->whereHas('entry', function ($query) use ($from, $to): void {
                $query->whereIn('status', [DocumentStatus::Completed->value, DocumentStatus::Reversed->value])
                    ->whereDate('entry_date', '>=', $from)
                    ->whereDate('entry_date', '<=', $to);
            })
            ->get(['debit', 'credit']);

        $net = '0.00';

        foreach ($lines as $line) {
            $movement = $creditMinusDebit
                ? Money::of((string) $line->credit)->sub((string) $line->debit)
                : Money::of((string) $line->debit)->sub((string) $line->credit);
            $net = Money::of($net)->add($movement)->amount();
        }

        return $net;
    }

    /**
     * @param  array<int, string>  $expected
     */
    private function assertShares(ProfitAllocation $allocation, array $expected, string $total): void
    {
        $lines = $allocation->lines()->orderBy('id')->get();
        $this->assertCount(count($expected), $lines);
        $sum = '0.00';

        foreach ($lines as $line) {
            $this->assertSame($expected[$line->partner_id], (string) $line->amount);
            $sum = Money::of($sum)->add((string) $line->amount)->amount();
        }

        $this->assertSame($total, $sum);
    }

    private function postIncome(User $actor, string $date, string $amount): JournalEntry
    {
        $cash = $this->cashAccount();

        return app(JournalEntryService::class)->post($actor, $actor, $date, 'Income '.$date, [
            ['account_code' => ChartAccountCode::Cash, 'financial_account_id' => $cash->id, 'debit' => $amount, 'credit' => '0.00'],
            ['account_code' => ChartAccountCode::OtherRevenue, 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    private function postExpense(User $actor, string $date, string $account, string $amount): JournalEntry
    {
        $cash = $this->cashAccount();

        return app(JournalEntryService::class)->post($actor, $actor, $date, 'Expense '.$date, [
            ['account_code' => $account, 'debit' => $amount, 'credit' => '0.00'],
            ['account_code' => ChartAccountCode::Cash, 'financial_account_id' => $cash->id, 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    private function assertJournalBalances(?JournalEntry $entry): void
    {
        $this->assertInstanceOf(JournalEntry::class, $entry);
        $entry->loadMissing('lines');
        $debit = '0.00';
        $credit = '0.00';

        foreach ($entry->lines as $line) {
            $debit = Money::of($debit)->add((string) $line->debit)->amount();
            $credit = Money::of($credit)->add((string) $line->credit)->amount();
        }

        $this->assertSame($debit, $credit);
    }
}
