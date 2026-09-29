<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ImmutableDocumentException;
use App\Models\FinancialAccount;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Ledger\PartnerFinancePoster;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class WithdrawalService
{
    public function __construct(
        private readonly PartnerFinanceGuard $guard,
        private readonly ApprovalService $approvals,
        private readonly PartnerFinancePoster $poster,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{partner_id: int, amount: string|int, transaction_date: string, reason: string, payment_method: string, financial_account_id: int, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): PartnerWithdrawal
    {
        return DB::transaction(function () use ($actor, $attributes): PartnerWithdrawal {
            $this->guard->assertOwnPartner($actor, (int) $attributes['partner_id']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $method = PaymentMethod::from($attributes['payment_method']);
            $this->guard->assertAccount($account, $method);

            $withdrawal = PartnerWithdrawal::query()->create([
                'partner_id' => $attributes['partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'reason' => $attributes['reason'],
                'payment_method' => $method,
                'financial_account_id' => $account->id,
                'reference' => Sequence::next(PartnerWithdrawal::class, 'reference', 'WDR'),
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit(
                $withdrawal,
                ApprovalRequestType::Withdrawal,
                (string) $withdrawal->amount,
                $actor,
                $withdrawal->note,
            );

            $this->audit->record(AuditAction::Created, $withdrawal, null, $this->snapshot($withdrawal), $actor);

            return $withdrawal->load('approvalRequest');
        });
    }

    /**
     * @param  array{partner_id: int, amount: string|int, transaction_date: string, reason: string, payment_method: string, financial_account_id: int, note?: string|null}  $attributes
     */
    public function update(PartnerWithdrawal $withdrawal, User $actor, array $attributes): PartnerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $actor, $attributes): PartnerWithdrawal {
            $withdrawal = PartnerWithdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $withdrawal->isEditable()) {
                throw new ImmutableDocumentException('Approved records cannot be edited.');
            }

            $this->guard->assertOwnPartner($actor, (int) $attributes['partner_id']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $method = PaymentMethod::from($attributes['payment_method']);
            $this->guard->assertAccount($account, $method);

            $before = $this->snapshot($withdrawal);
            $withdrawal->fill([
                'partner_id' => $attributes['partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'reason' => $attributes['reason'],
                'payment_method' => $method,
                'financial_account_id' => $account->id,
                'note' => $attributes['note'] ?? null,
            ]);
            $withdrawal->save();

            $this->approvals->syncAmount($withdrawal, ApprovalRequestType::Withdrawal, (string) $withdrawal->amount);
            $this->audit->record(AuditAction::Updated, $withdrawal, $before, $this->snapshot($withdrawal), $actor);

            return $withdrawal;
        });
    }

    public function cancel(PartnerWithdrawal $withdrawal, User $actor, ?string $comment = null): PartnerWithdrawal
    {
        $request = $withdrawal->approvalRequest()->firstOrFail();
        $this->approvals->cancel($request, $actor, $comment);

        return $withdrawal->fresh();
    }

    public function reverse(PartnerWithdrawal $withdrawal, User $actor, string $reason): PartnerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $actor, $reason): PartnerWithdrawal {
            $withdrawal = PartnerWithdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== DocumentStatus::Approved) {
                throw new ImmutableDocumentException('Only an approved withdrawal can be reversed.');
            }

            $this->poster->reverse($withdrawal, $actor, $reason);
            $withdrawal->status = DocumentStatus::Reversed;
            $withdrawal->save();

            $this->audit->record(AuditAction::Reversed, $withdrawal, null, [
                'status' => DocumentStatus::Reversed->value,
                'reason' => $reason,
            ], $actor);

            return $withdrawal;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PartnerWithdrawal $withdrawal): array
    {
        return [
            'reference' => $withdrawal->reference,
            'partner_id' => $withdrawal->partner_id,
            'amount' => (string) $withdrawal->amount,
            'transaction_date' => $withdrawal->transaction_date?->toDateString(),
            'reason' => $withdrawal->reason,
            'payment_method' => $withdrawal->payment_method->value,
            'financial_account_id' => $withdrawal->financial_account_id,
            'status' => $withdrawal->status->value,
            'note' => $withdrawal->note,
        ];
    }
}
