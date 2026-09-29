<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Exceptions\SelfApprovalException;
use App\Models\Expense;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\Promotion;
use App\Models\PromotionPartnerExpense;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\PartnerStatementService;
use App\Services\Spending\ExpenseService;
use App\Services\Spending\PromotionContributionService;
use App\Services\Spending\PromotionService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Collection;

class SpendingTest extends FinanceTestCase
{
    public function test_partner_paid_promotion_needs_approval_and_credits_partner_capital(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create(['name' => 'Rahim Uddin']);
        $cash = $this->cashAccount();
        $beforeCash = (string) $cash->current_balance;
        $beforeCapital = app(PartnerStatementService::class)->capitalBalance($partner);
        $promotion = $this->promotion($admin);

        $contribution = $this->contribution($admin, $promotion, $partner, 'partner', '2000.00');

        $this->assertSame(DocumentStatus::Pending, $contribution->status);
        $this->assertNull($contribution->journal_entry_id);
        $this->assertSame('0.00', (string) $promotion->fresh()->actual_amount);
        $this->assertSame($beforeCapital, app(PartnerStatementService::class)->capitalBalance($partner));

        try {
            app(ApprovalService::class)->approve($contribution->approvalRequest, $admin, 'Approving my own contribution.');
            $this->fail('Self-approval should have been refused.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $this->assertNull($contribution->fresh()->journal_entry_id);

        app(ApprovalService::class)->approve($contribution->approvalRequest, $accountant, 'Accepted.');

        $contribution->refresh()->load('journalEntry.lines.account');
        $cash->refresh();
        $statements = app(PartnerStatementService::class);
        $this->assertSame(DocumentStatus::Approved, $contribution->status);
        $this->assertSame('2000.00', (string) $promotion->fresh()->actual_amount);
        $this->assertSame($beforeCash, (string) $cash->current_balance);
        $this->assertSame(Money::of($beforeCapital)->add('2000.00')->amount(), $statements->capitalBalance($partner));
        $this->assertSame('2000.00', $statements->position($partner)['promotion_contribution']['amount']);
        $this->assertJournalBalances($contribution);

        $lines = $contribution->journalEntry->lines;
        $this->assertSame('2000.00', $this->side($lines, ChartAccountCode::PromotionExpense, 'debit'));
        $this->assertSame('2000.00', $this->side($lines, ChartAccountCode::PartnerCapital, 'credit'));
        $this->assertTrue($lines->contains(fn ($line): bool => $line->account?->code === ChartAccountCode::PartnerCapital && (int) $line->partner_id === $partner->id));

        $statement = $statements->statement($partner);
        $this->assertSame('2000.00', $statement[array_key_last($statement)]['credit']);
        $this->assertSame('2000.00', $statement[array_key_last($statement)]['running_balance']);
    }

    public function test_business_paid_promotion_credits_cash_and_not_partner_capital(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create();
        $cash = $this->cashAccount();
        $beforeCash = (string) $cash->current_balance;
        $beforeCapital = app(PartnerStatementService::class)->capitalBalance($partner);
        $promotion = $this->promotion($admin);

        $contribution = $this->contribution($admin, $promotion, $partner, 'business', '350.00', $cash->id);
        app(ApprovalService::class)->approve($contribution->approvalRequest, $accountant, 'Paid from the drawer.');

        $contribution->refresh()->load('journalEntry.lines.account');
        $cash->refresh();
        $statements = app(PartnerStatementService::class);
        $this->assertJournalBalances($contribution);
        $this->assertSame('350.00', (string) $promotion->fresh()->actual_amount);
        $this->assertSame(Money::of($beforeCash)->sub('350.00')->amount(), (string) $cash->current_balance);
        $this->assertSame($beforeCapital, $statements->capitalBalance($partner));
        $this->assertSame('0.00', $statements->position($partner)['promotion_contribution']['amount']);

        $lines = $contribution->journalEntry->lines;
        $this->assertSame('350.00', $this->side($lines, ChartAccountCode::PromotionExpense, 'debit'));
        $this->assertSame('350.00', $this->side($lines, ChartAccountCode::Cash, 'credit'));
        $this->assertSame('0.00', $this->side($lines, ChartAccountCode::PartnerCapital, 'credit'));
    }

    public function test_rejected_promotion_contribution_posts_nothing(): void
    {
        $sales = $this->userWithRole(RoleName::SalesManager);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create();
        $promotion = $this->promotion($sales);
        $contribution = $this->contribution($sales, $promotion, $partner, 'partner', '900.00');

        app(ApprovalService::class)->reject($contribution->approvalRequest, $accountant, 'Not this campaign.');

        $contribution->refresh();
        $this->assertSame(DocumentStatus::Rejected, $contribution->status);
        $this->assertNull($contribution->journal_entry_id);
        $this->assertSame('0.00', (string) $promotion->fresh()->actual_amount);
        $this->assertSame('0.00', app(PartnerStatementService::class)->position($partner)['promotion_contribution']['amount']);
    }

    public function test_expenses_need_approval_and_credit_the_partner_or_cash(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $super = $this->userWithRole(RoleName::SuperAdmin);
        $partner = Partner::factory()->create(['name' => 'Fatema Akter']);
        $cash = $this->cashAccount();
        $beforeCash = (string) $cash->current_balance;
        $beforeCapital = app(PartnerStatementService::class)->capitalBalance($partner);

        $personal = app(ExpenseService::class)->create($admin, [
            'category' => 'electricity',
            'amount' => '400.00',
            'transaction_date' => '2026-05-15',
            'description' => 'Shop electricity paid by the partner.',
            'partner_id' => $partner->id,
        ]);

        $this->assertSame(DocumentStatus::Pending, $personal->status);
        $this->assertNull($personal->journal_entry_id);

        try {
            app(ApprovalService::class)->approve($personal->approvalRequest, $admin, 'Approving my own expense.');
            $this->fail('Self-approval should have been refused.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $this->assertNull($personal->fresh()->journal_entry_id);
        $this->assertSame($beforeCash, (string) $cash->fresh()->current_balance);

        app(ApprovalService::class)->approve($personal->approvalRequest, $super, 'Capital credit.');

        $personal->refresh()->load('journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Approved, $personal->status);
        $this->assertJournalBalances($personal);
        $this->assertSame('400.00', $this->side($personal->journalEntry->lines, ChartAccountCode::Electricity, 'debit'));
        $this->assertSame('400.00', $this->side($personal->journalEntry->lines, ChartAccountCode::PartnerCapital, 'credit'));
        $this->assertSame($beforeCash, (string) $cash->fresh()->current_balance);
        $this->assertSame(Money::of($beforeCapital)->add('400.00')->amount(), app(PartnerStatementService::class)->capitalBalance($partner));
        $this->assertSame('400.00', app(PartnerStatementService::class)->position($partner)['expenses']['amount']);

        $rent = app(ExpenseService::class)->create($admin, [
            'category' => 'rent',
            'amount' => '250.00',
            'transaction_date' => '2026-05-01',
            'description' => 'Shop rent from the drawer.',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ]);
        app(ApprovalService::class)->approve($rent->approvalRequest, $super, 'Rent posted.');

        $rent->refresh()->load('journalEntry.lines.account');
        $cash->refresh();
        $this->assertJournalBalances($rent);
        $this->assertSame('250.00', $this->side($rent->journalEntry->lines, ChartAccountCode::Rent, 'debit'));
        $this->assertSame('250.00', $this->side($rent->journalEntry->lines, ChartAccountCode::Cash, 'credit'));
        $this->assertSame(Money::of($beforeCash)->sub('250.00')->amount(), (string) $cash->current_balance);
    }

    public function test_rejected_expense_posts_nothing(): void
    {
        $accountant = $this->userWithRole(RoleName::Accountant);
        $admin = $this->userWithRole(RoleName::Admin);
        $cash = $this->cashAccount();
        $before = (string) $cash->current_balance;

        $expense = app(ExpenseService::class)->create($accountant, [
            'category' => 'packaging',
            'amount' => '180.00',
            'transaction_date' => '2026-05-18',
            'description' => 'Boxes we decided not to buy.',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ]);

        app(ApprovalService::class)->reject($expense->approvalRequest, $admin, 'Skip it.');

        $expense->refresh();
        $cash->refresh();
        $this->assertSame(DocumentStatus::Rejected, $expense->status);
        $this->assertNull($expense->journal_entry_id);
        $this->assertSame($before, (string) $cash->current_balance);
    }

    public function test_spending_permissions_gate_the_screens(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partnerUser = $this->userWithRole(RoleName::Partner);
        $sales = $this->userWithRole(RoleName::SalesManager);

        $this->actingAs($viewer)->get(route('promotions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Promotions/Index'));

        $this->actingAs($viewer)->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Expenses/Index'));

        $this->actingAs($viewer)->post(route('promotions.store'), [
            'name' => 'Denied',
            'platform' => 'facebook',
            'starts_on' => '2026-04-01',
            'budget' => '100.00',
            'status' => 'active',
        ])->assertForbidden();

        $this->actingAs($partnerUser)->get(route('expenses.index'))->assertForbidden();

        $this->actingAs($sales)->post(route('promotions.store'), [
            'name' => 'Weekend Instagram',
            'platform' => 'instagram',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-07',
            'budget' => '1500.00',
            'status' => 'planned',
            'description' => 'Stories for the new wallets.',
        ])->assertRedirect()->assertSessionHas('success', 'Promotion saved.');

        $this->assertDatabaseHas('promotions', ['name' => 'Weekend Instagram', 'status' => 'planned']);
    }

    private function promotion(User $actor): Promotion
    {
        return app(PromotionService::class)->create($actor, [
            'name' => 'Boishakh ads',
            'platform' => 'facebook',
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'budget' => '8000.00',
            'status' => 'active',
            'description' => 'Sponsored posts.',
        ]);
    }

    private function contribution(
        User $actor,
        Promotion $promotion,
        Partner $partner,
        string $fundedBy,
        string $amount,
        ?int $accountId = null,
    ): PromotionPartnerExpense {
        return app(PromotionContributionService::class)->create($actor, $promotion, [
            'partner_id' => $partner->id,
            'funded_by' => $fundedBy,
            'amount' => $amount,
            'transaction_date' => '2026-04-10',
            'payment_method' => $fundedBy === 'business' ? 'cash' : null,
            'financial_account_id' => $accountId,
            'note' => 'Test contribution.',
        ]);
    }

    private function assertJournalBalances(PromotionPartnerExpense|Expense $document): void
    {
        $debits = '0.00';
        $credits = '0.00';

        foreach ($document->journalEntry->lines as $line) {
            $debits = Money::of($debits)->add((string) $line->debit)->amount();
            $credits = Money::of($credits)->add((string) $line->credit)->amount();
        }

        $this->assertSame($debits, $credits);
        $this->assertNotSame('0.00', $debits);
    }

    /**
     * @param  Collection<int, JournalEntryLine>  $lines
     */
    private function side($lines, string $code, string $column): string
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
