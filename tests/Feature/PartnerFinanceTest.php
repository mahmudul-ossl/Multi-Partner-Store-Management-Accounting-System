<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Exceptions\ImmutableDocumentException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\ApprovalThreshold;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Notifications\ApprovalActivity;
use App\Services\Approvals\ApprovalThresholdResolver;
use App\Services\Finance\PartnerStatementService;
use App\Services\Finance\WithdrawalService;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class PartnerFinanceTest extends FinanceTestCase
{
    public function test_critical_withdrawal_blocks_self_approval_then_posts_a_balanced_journal(): void
    {
        ApprovalThreshold::query()
            ->where('request_type', ApprovalRequestType::Withdrawal)
            ->where('min_amount', '10000.01')
            ->update(['required_approvals' => 1]);

        [$partnerUser, $partner] = $this->linkedPartner('Rahim Uddin');
        $approver = $this->userWithRole(RoleName::Accountant);
        $cashBefore = (string) $this->cashAccount()->current_balance;

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $approval = $withdrawal->approvalRequest()->firstOrFail();
        $this->assertSame(DocumentStatus::Pending, $withdrawal->status);
        $this->assertNull($withdrawal->journal_entry_id);
        $this->assertSame('50000.00', (string) $withdrawal->amount);

        $this->actingAs($partnerUser)
            ->postJson(route('approvals.approve', $approval), ['comment' => 'I approve myself'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot approve your own transaction.');

        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertSame($cashBefore, (string) $this->cashAccount()->fresh()->current_balance);

        $this->actingAs($approver)
            ->post(route('approvals.approve', $approval), ['comment' => 'Paid from cash'])
            ->assertRedirect(route('approvals.show', $approval));

        $withdrawal->refresh();
        $approval->refresh();
        $this->assertSame(DocumentStatus::Approved, $withdrawal->status);
        $this->assertSame(ApprovalStatus::Approved, $approval->status);
        $this->assertNotNull($withdrawal->journal_entry_id);

        $entry = $withdrawal->journalEntry()->with('lines.account')->firstOrFail();
        $this->assertSame(DocumentStatus::Completed, $entry->status);
        $debits = '0.00';
        $credits = '0.00';

        foreach ($entry->lines as $line) {
            $debits = bcadd($debits, (string) $line->debit, 2);
            $credits = bcadd($credits, (string) $line->credit, 2);
        }

        $this->assertSame('50000.00', $debits);
        $this->assertSame($debits, $credits);
        $this->assertTrue($entry->lines->contains(
            fn (JournalEntryLine $line): bool => $line->account->code === ChartAccountCode::PartnerWithdrawals && (string) $line->debit === '50000.00',
        ));
        $this->assertSame(bcsub($cashBefore, '50000.00', 2), (string) $this->cashAccount()->fresh()->current_balance);

        $this->actingAs($partnerUser)
            ->putJson(route('withdrawals.update', $withdrawal), $this->withdrawalPayload($partner, '1000.00'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Approved records cannot be edited.');

        try {
            app(WithdrawalService::class)->update($withdrawal->fresh(), $partnerUser, $this->withdrawalPayload($partner, '1000.00'));
            $this->fail('The service accepted an edit of an approved withdrawal.');
        } catch (ImmutableDocumentException $exception) {
            $this->assertSame('Approved records cannot be edited.', $exception->getMessage());
        }

        $this->assertSame('50000.00', (string) $withdrawal->fresh()->amount);
    }

    public function test_one_hundred_fifty_thousand_needs_three_distinct_approvers(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Fatema Akter');
        $first = $this->userWithRole(RoleName::Accountant);
        $second = $this->userWithRole(RoleName::Admin);
        $third = $this->userWithRole(RoleName::Admin);

        $this->assertSame(3, app(ApprovalThresholdResolver::class)->requiredFor(ApprovalRequestType::Withdrawal, '150000.00'));
        $this->assertSame(2, app(ApprovalThresholdResolver::class)->requiredFor(ApprovalRequestType::Withdrawal, '50000.00'));

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '150000.00'))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $approval = $withdrawal->approvalRequest;

        $this->actingAs($first)->post(route('approvals.approve', $approval), ['comment' => 'First'])->assertRedirect();
        $this->assertSame(ApprovalStatus::PartiallyApproved, $approval->fresh()->status);
        $this->assertNull($withdrawal->fresh()->journal_entry_id);

        $this->actingAs($first)
            ->postJson(route('approvals.approve', $approval), ['comment' => 'Again'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You have already approved this request.');

        $this->actingAs($second)->post(route('approvals.approve', $approval), ['comment' => 'Second'])->assertRedirect();
        $this->assertNull($withdrawal->fresh()->journal_entry_id);

        $this->actingAs($third)->post(route('approvals.approve', $approval), ['comment' => 'Third'])->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(DocumentStatus::Approved, $withdrawal->status);
        $this->assertNotNull($withdrawal->journal_entry_id);
        $this->assertSame(3, $approval->fresh()->completed_approvals);
    }

    public function test_rejection_does_not_post_a_journal_or_change_cash(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Karim Hossain');
        $approver = $this->userWithRole(RoleName::Accountant);
        $cashBefore = (string) $this->cashAccount()->current_balance;

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '8000.00'))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();

        $this->actingAs($approver)
            ->post(route('approvals.reject', $withdrawal->approvalRequest), ['comment' => 'Not this month'])
            ->assertRedirect();

        $this->assertSame(DocumentStatus::Rejected, $withdrawal->fresh()->status);
        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertSame($cashBefore, (string) $this->cashAccount()->fresh()->current_balance);
    }

    public function test_a_transfer_moves_capital_and_leaves_cash_unchanged(): void
    {
        [$fromUser, $from] = $this->linkedPartner('Nusrat Jahan');
        $to = Partner::factory()->create(['name' => 'Shahidul Islam']);
        $approver = $this->userWithRole(RoleName::Accountant);
        $cashBefore = (string) $this->cashAccount()->current_balance;

        $this->actingAs($fromUser)->post(route('transfers.store'), [
            'from_partner_id' => $from->id,
            'to_partner_id' => $to->id,
            'amount' => '3000.00',
            'transaction_date' => '2026-09-28',
            'note' => 'Capital reallocation',
        ])->assertRedirect();

        $transfer = PartnerTransfer::query()->firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $transfer->approvalRequest))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(DocumentStatus::Approved, $transfer->status);
        $this->assertSame($cashBefore, (string) $this->cashAccount()->fresh()->current_balance);
        $this->assertTrue(
            $transfer->journalEntry->lines()->whereNotNull('financial_account_id')->doesntExist(),
        );

        $statements = app(PartnerStatementService::class);
        $this->assertSame('-3000.00', $statements->capitalBalance($from));
        $this->assertSame('3000.00', $statements->capitalBalance($to));
    }

    public function test_statement_running_balance_matches_the_ledger(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Ayesha Siddiqua');
        $other = Partner::factory()->create();
        $approver = $this->userWithRole(RoleName::Accountant);
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($partnerUser)->post(route('investments.store'), [
            'partner_id' => $partner->id,
            'amount' => '50000.00',
            'transaction_date' => '2026-09-01',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
        ])->assertRedirect();

        $investment = PartnerInvestment::query()->firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $investment->approvalRequest))->assertRedirect();
        $this->actingAs($admin)->post(route('approvals.approve', $investment->approvalRequest))->assertRedirect();

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '20000.00', [
                'transaction_date' => '2026-09-10',
            ]))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $withdrawal->approvalRequest))->assertRedirect();
        $this->actingAs($admin)->post(route('approvals.approve', $withdrawal->approvalRequest))->assertRedirect();

        $this->actingAs($admin)->post(route('transfers.store'), [
            'from_partner_id' => $partner->id,
            'to_partner_id' => $other->id,
            'amount' => '5000.00',
            'transaction_date' => '2026-09-20',
        ])->assertRedirect();

        $transfer = PartnerTransfer::query()->firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $transfer->approvalRequest))->assertRedirect();

        $lines = app(PartnerStatementService::class)->statement($partner);

        $this->assertSame(['50000.00', '30000.00', '25000.00'], array_column($lines, 'running_balance'));
        $this->assertSame('25000.00', app(PartnerStatementService::class)->capitalBalance($partner));
        $this->assertSame('25000.00', $lines[array_key_last($lines)]['running_balance']);

        $this->actingAs($admin)
            ->get(route('partners.statement', $partner))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Partners/Statement')
                ->where('lines.2.running_balance', '25000.00')
                ->where('current_capital.amount', '25000.00'));
    }

    public function test_direct_requests_cannot_bypass_approval_or_post_a_journal(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Tanvir Ahmed');
        $outsider = $this->userWithRole(RoleName::Viewer);
        [, $other] = $this->linkedPartner('Mahmuda Khatun');

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '4000.00', [
                'status' => 'approved',
                'journal_entry_id' => 99,
            ]))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $this->assertSame(DocumentStatus::Pending, $withdrawal->status);
        $this->assertNull($withdrawal->journal_entry_id);
        $this->assertSame(0, JournalEntry::query()->count());

        $this->actingAs($outsider)
            ->postJson(route('approvals.approve', $withdrawal->approvalRequest))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to decide this request.');

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($other, '1000.00'))
            ->assertForbidden();

        $this->post('/withdrawals/'.$withdrawal->id.'/force-post')->assertNotFound();
    }

    public function test_request_notification_goes_to_approvers_and_not_the_requester(): void
    {
        Notification::fake();
        [$partnerUser, $partner] = $this->linkedPartner('Rahim Notify');
        $approver = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '4000.00'))
            ->assertRedirect();

        Notification::assertSentTo($approver, ApprovalActivity::class, function (ApprovalActivity $notification): bool {
            return str_contains($notification->message, '৳4,000.00')
                && str_contains($notification->message, 'Rahim Notify');
        });
        Notification::assertNotSentTo($partnerUser, ApprovalActivity::class);
    }

    public function test_journal_entries_reject_unbalanced_lines_and_cannot_be_deleted(): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $source = Partner::factory()->create();

        $this->expectException(UnbalancedEntryException::class);

        try {
            app(JournalEntryService::class)->post($source, $actor, '2026-09-28', 'Broken', [
                ['account_code' => ChartAccountCode::Cash, 'financial_account_id' => $this->cashAccount()->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => ChartAccountCode::PartnerCapital, 'partner_id' => $source->id, 'debit' => '0.00', 'credit' => '9.00'],
            ]);
        } finally {
            $entry = app(JournalEntryService::class)->post($source, $actor, '2026-09-28', 'Opening', [
                ['account_code' => ChartAccountCode::Cash, 'financial_account_id' => $this->cashAccount()->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => ChartAccountCode::PartnerCapital, 'partner_id' => $source->id, 'debit' => '0.00', 'credit' => '10.00'],
            ]);

            try {
                $entry->delete();
                $this->fail('Journal entry was deleted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
            }
        }
    }

    public function test_settings_screen_changes_how_many_approvals_an_amount_needs(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('settings.approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Approvals'));

        $band = ApprovalThreshold::query()
            ->where('request_type', ApprovalRequestType::Investment)
            ->where('min_amount', '0.00')
            ->firstOrFail();

        $this->actingAs($admin)->put(route('settings.approvals.update', $band), [
            'request_type' => 'investment',
            'min_amount' => '0.00',
            'max_amount' => '10000.00',
            'required_approvals' => 2,
        ])->assertRedirect();

        $this->assertSame(2, app(ApprovalThresholdResolver::class)->requiredFor(ApprovalRequestType::Investment, '5000'));
    }
}
