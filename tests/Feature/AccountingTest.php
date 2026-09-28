<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Exceptions\ApprovalStateException;
use App\Exceptions\SelfApprovalException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ManualJournal;
use App\Models\User;
use App\Services\Accounting\AccountTransferService;
use App\Services\Accounting\FinancialAccountService;
use App\Services\Accounting\JournalReversalService;
use App\Services\Accounting\LedgerReportService;
use App\Services\Accounting\ManualJournalService;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\InvestmentService;
use App\Support\Money;

class AccountingTest extends FinanceTestCase
{
    public function test_unbalanced_manual_journal_is_rejected(): void
    {
        $accountant = $this->userWithRole(RoleName::Accountant);

        try {
            app(ManualJournalService::class)->create($accountant, [
                'entry_date' => '2026-04-01',
                'description' => 'Unbalanced',
                'lines' => [
                    ['account_code' => '5500', 'debit' => '10.00', 'credit' => '0.00'],
                    ['account_code' => '2100', 'debit' => '0.00', 'credit' => '9.00'],
                ],
            ]);
            $this->fail('An unbalanced journal was stored.');
        } catch (UnbalancedEntryException $exception) {
            $this->assertStringContainsString('do not equal', $exception->getMessage());
        }

        $this->assertSame(0, ManualJournal::query()->count());
    }

    public function test_unbalanced_journal_request_is_rejected(): void
    {
        $accountant = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($accountant)
            ->postJson(route('accounting.manual-journals.store'), [
                'entry_date' => '2026-04-01',
                'description' => 'Unbalanced',
                'lines' => [
                    ['account_code' => '5500', 'debit' => '10.00', 'credit' => '0.00'],
                    ['account_code' => '2100', 'debit' => '0.00', 'credit' => '9.00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Debits 10.00 do not equal credits 9.00.');

        $this->assertSame(0, ManualJournal::query()->count());
    }

    public function test_opening_balance_posts_through_the_ledger_and_matches_the_cache(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = $this->openCash($admin, 'Drawer', '5000.00');

        $this->assertSame('5000.00', (string) $account->opening_balance);
        $this->assertSame('5000.00', (string) $account->current_balance);
        $this->assertSame('5000.00', app(FinancialAccountService::class)->ledgerBalance($account));
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => $account->getMorphClass(),
            'source_id' => $account->id,
            'description' => 'Opening balance · Drawer',
        ]);
        $this->assertTrue(
            JournalEntryLine::query()->where('chart_of_account_id', ChartOfAccount::query()->where('code', '3300')->value('id'))
                ->where('credit', '5000.00')
                ->exists()
        );
    }

    public function test_reconcile_replaces_a_drifted_cache_from_the_ledger(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = $this->openCash($admin, 'Drawer drift', '750.00');
        $account->current_balance = '1.00';
        $account->save();

        $synced = app(FinancialAccountService::class)->syncFromLedger($account, $admin);

        $this->assertSame('750.00', (string) $synced->current_balance);
        $this->assertSame((string) $synced->current_balance, app(FinancialAccountService::class)->ledgerBalance($synced));

        $this->actingAs($admin)
            ->post(route('accounting.accounts.reconcile', $account))
            ->assertRedirect(route('accounting.accounts.show', $account));
    }

    public function test_reversal_nets_the_opening_balance_to_zero(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = $this->openCash($admin, 'Drawer undo', '800.00');
        $entry = JournalEntry::query()
            ->where('source_type', $account->getMorphClass())
            ->where('source_id', $account->id)
            ->firstOrFail();

        app(JournalReversalService::class)->reverse($entry, $admin, 'Undo opening');

        $account->refresh();
        $this->assertSame('800.00', (string) $account->opening_balance);
        $this->assertSame('0.00', (string) $account->current_balance);
        $this->assertSame('0.00', app(FinancialAccountService::class)->ledgerBalance($account));

        $debits = '0.00';
        $credits = '0.00';

        foreach (JournalEntryLine::query()->where('financial_account_id', $account->id)->get() as $line) {
            $debits = Money::of($debits)->add((string) $line->debit)->amount();
            $credits = Money::of($credits)->add((string) $line->credit)->amount();
        }

        $this->assertSame($debits, $credits);
        $this->assertSame(0, Money::of($debits)->compare($credits));
    }

    public function test_bank_to_wallet_transfer_moves_both_caches_and_stays_balanced(): void
    {
        [$admin, $accountant] = $this->pair();
        $bank = $this->openAccount($admin, 'Test Bank', 'bank', '1010', '10000.00');
        $wallet = $this->openAccount($admin, 'Test bKash', 'mobile_wallet', '1020', '0.00');

        $transfer = app(AccountTransferService::class)->create($admin, [
            'from_financial_account_id' => $bank->id,
            'to_financial_account_id' => $wallet->id,
            'amount' => '2500.00',
            'transaction_date' => '2026-04-12',
            'note' => 'Top up',
        ]);
        app(ApprovalService::class)->approve($transfer->approvalRequest, $accountant, 'Sent.');

        $bank->refresh();
        $wallet->refresh();
        $service = app(FinancialAccountService::class);

        $this->assertSame('7500.00', (string) $bank->current_balance);
        $this->assertSame('2500.00', (string) $wallet->current_balance);
        $this->assertSame((string) $bank->current_balance, $service->ledgerBalance($bank));
        $this->assertSame((string) $wallet->current_balance, $service->ledgerBalance($wallet));

        $entry = $transfer->fresh()->journalEntry()->with('lines')->firstOrFail();
        $debits = '0.00';
        $credits = '0.00';

        foreach ($entry->lines as $line) {
            $debits = Money::of($debits)->add((string) $line->debit)->amount();
            $credits = Money::of($credits)->add((string) $line->credit)->amount();
        }

        $this->assertSame('2500.00', $debits);
        $this->assertSame($debits, $credits);
    }

    public function test_transfer_on_one_chart_moves_the_caches_and_nets_in_the_general_ledger(): void
    {
        [$admin, $accountant] = $this->pair();
        $from = $this->openAccount($admin, 'Bank A', 'bank', '1010', '1000.00', '2026-01-03');
        $to = $this->openAccount($admin, 'Bank B', 'bank', '1010', '0.00');

        $transfer = app(AccountTransferService::class)->create($accountant, [
            'from_financial_account_id' => $from->id,
            'to_financial_account_id' => $to->id,
            'amount' => '100.00',
            'transaction_date' => '2026-05-01',
            'note' => null,
        ]);
        app(ApprovalService::class)->approve($transfer->approvalRequest, $admin, 'Moved.');

        $chart = ChartOfAccount::query()->where('code', '1010')->firstOrFail();
        $report = app(LedgerReportService::class)->generalLedger($chart, '2026-05-01', '2026-05-01');

        $this->assertSame('1000.00', $report['opening_balance']['amount']);
        $this->assertSame('1000.00', $report['closing_balance']['amount']);
        $from->refresh();
        $to->refresh();
        $this->assertSame('900.00', (string) $from->current_balance);
        $this->assertSame('100.00', (string) $to->current_balance);
    }

    public function test_self_approval_of_a_manual_journal_is_blocked(): void
    {
        $accountant = $this->userWithRole(RoleName::Accountant);
        $journal = $this->draftJournal($accountant, 'Own charge');

        $this->expectException(SelfApprovalException::class);
        $this->expectExceptionMessage('You cannot approve your own transaction.');

        app(ApprovalService::class)->approve($journal->approvalRequest, $accountant, 'Mine.');
    }

    public function test_general_ledger_opening_balance_uses_the_date_filter(): void
    {
        [$admin, $accountant] = $this->pair();
        $cash = $this->cashAccount();
        $this->postExpense($accountant, $admin, '2026-01-15', '100.00');
        $this->postExpense($accountant, $admin, '2026-03-01', '40.00');

        $account = ChartOfAccount::query()->where('code', '5500')->firstOrFail();
        $report = app(LedgerReportService::class)->generalLedger($account, '2026-02-01', '2026-03-31');

        $this->assertSame('100.00', $report['opening_balance']['amount']);
        $this->assertCount(1, $report['lines']);
        $this->assertSame('40.00', $report['lines'][0]['debit']['amount']);
        $this->assertSame('140.00', $report['lines'][0]['running_balance']['amount']);
        $this->assertSame('140.00', $report['closing_balance']['amount']);

        $this->actingAs($accountant)
            ->get(route('accounting.reports.ledger', ['account' => $account->id, 'from' => '2026-02-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/GeneralLedger')
                ->where('report.opening_balance.amount', '100.00')
                ->where('report.closing_balance.amount', '140.00'));
    }

    public function test_permissions_and_system_accounts(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partner = $this->linkedPartner('Partner Z')[0];
        $accountant = $this->userWithRole(RoleName::Accountant);
        $admin = $this->userWithRole(RoleName::Admin);
        $cashChart = ChartOfAccount::query()->where('code', '1000')->firstOrFail();

        $this->actingAs($viewer)->get(route('accounting.chart.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Chart'));
        $this->actingAs($viewer)->get(route('accounting.reports.ledger'))->assertOk();
        $this->actingAs($viewer)->get(route('accounting.reports.cash'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/CashReport'));
        $this->actingAs($viewer)->get(route('accounting.reports.bank'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/BankReport'));
        $this->actingAs($viewer)->post(route('accounting.chart.store'), $this->chartPayload())->assertForbidden();
        $this->actingAs($viewer)->post(route('accounting.accounts.store'), [
            'name' => 'Viewer cash',
            'type' => 'cash',
            'chart_of_account_id' => $cashChart->id,
            'opening_balance' => '0.00',
        ])->assertForbidden();
        $this->actingAs($viewer)->post(route('accounting.manual-journals.store'), [
            'entry_date' => '2026-04-01',
            'description' => 'Nope',
            'lines' => [],
        ])->assertForbidden();

        $this->actingAs($partner)->get(route('accounting.chart.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('accounting.entries.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('accounting.reports.cash'))->assertForbidden();

        $this->actingAs($accountant)->post(route('accounting.chart.store'), $this->chartPayload())->assertForbidden();
        $this->actingAs($accountant)->get(route('accounting.accounts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Accounts'));

        $this->actingAs($admin)->post(route('accounting.chart.store'), $this->chartPayload())->assertRedirect();
        $created = ChartOfAccount::query()->where('code', '5610')->firstOrFail();
        $this->assertFalse($created->is_system);

        $this->actingAs($admin)->putJson(route('accounting.chart.update', $cashChart), [
            'code' => '1999',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ])->assertStatus(422)->assertJsonPath('message', 'System account code cannot be changed.');

        $this->actingAs($admin)->put(route('accounting.chart.update', $cashChart), [
            'code' => '1000',
            'name' => 'Cash on hand',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ])->assertRedirect();
        $this->assertSame('Cash on hand', $cashChart->fresh()->name);

        $this->actingAs($admin)->deleteJson(route('accounting.chart.destroy', $cashChart))
            ->assertStatus(422)
            ->assertJsonPath('message', 'System accounts cannot be deleted.');

        $this->actingAs($admin)->delete(route('accounting.chart.destroy', $created))->assertRedirect();
        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '5610']);
    }

    public function test_partner_journals_are_not_reversed_from_the_ledger_screen(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Partner A');
        $accountant = $this->userWithRole(RoleName::Accountant);

        $investment = app(InvestmentService::class)->create($partnerUser, [
            'partner_id' => $partner->id,
            'amount' => '1000.00',
            'transaction_date' => '2026-06-01',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
            'note' => null,
        ]);
        app(ApprovalService::class)->approve($investment->approvalRequest, $accountant, 'Received.');

        $this->expectException(ApprovalStateException::class);
        $this->expectExceptionMessage('Reverse this entry from the partner document.');

        app(JournalReversalService::class)->reverse(
            $investment->fresh()->journalEntry,
            $this->userWithRole(RoleName::Admin),
            'Not from here',
        );
    }

    public function test_rejected_manual_journal_does_not_post(): void
    {
        [$admin, $accountant] = $this->pair();
        $journal = $this->draftJournal($accountant, 'Rejected charge');

        app(ApprovalService::class)->reject($journal->approvalRequest, $admin, 'Not this month.');

        $this->assertNull($journal->fresh()->journal_entry_id);
        $this->assertSame('rejected', $journal->fresh()->status->value);
        $this->assertSame(0, JournalEntry::query()->count());
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function pair(): array
    {
        return [
            $this->userWithRole(RoleName::Admin),
            $this->userWithRole(RoleName::Accountant),
        ];
    }

    private function openCash(User $actor, string $name, string $amount): FinancialAccount
    {
        return $this->openAccount($actor, $name, 'cash', '1000', $amount);
    }

    private function openAccount(User $actor, string $name, string $type, string $code, string $amount, string $date = '2026-01-01'): FinancialAccount
    {
        return app(FinancialAccountService::class)->create($actor, [
            'name' => $name,
            'type' => $type,
            'chart_of_account_id' => ChartOfAccount::query()->where('code', $code)->value('id'),
            'opening_balance' => $amount,
            'opening_date' => $date,
        ]);
    }

    private function draftJournal(User $creator, string $description): ManualJournal
    {
        return app(ManualJournalService::class)->create($creator, [
            'entry_date' => '2026-04-02',
            'description' => $description,
            'lines' => [
                ['account_code' => '5500', 'debit' => '25.00', 'credit' => '0.00'],
                ['account_code' => '1000', 'financial_account_id' => $this->cashAccount()->id, 'debit' => '0.00', 'credit' => '25.00'],
            ],
        ]);
    }

    private function postExpense(User $creator, User $approver, string $date, string $amount): void
    {
        $journal = app(ManualJournalService::class)->create($creator, [
            'entry_date' => $date,
            'description' => 'Charge '.$date,
            'lines' => [
                ['account_code' => '5500', 'debit' => $amount, 'credit' => '0.00'],
                ['account_code' => '1000', 'financial_account_id' => $this->cashAccount()->id, 'debit' => '0.00', 'credit' => $amount],
            ],
        ]);
        app(ApprovalService::class)->approve($journal->approvalRequest, $approver, 'Posted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function chartPayload(): array
    {
        return [
            'code' => '5610',
            'name' => 'Utilities',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'parent_id' => ChartOfAccount::query()->where('code', '5600')->value('id'),
            'is_active' => true,
            'description' => 'Utilities',
        ];
    }
}
