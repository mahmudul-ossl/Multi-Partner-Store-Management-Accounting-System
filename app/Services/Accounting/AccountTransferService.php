<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class AccountTransferService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{from_financial_account_id: int, to_financial_account_id: int, amount: string|int, transaction_date: string, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): AccountTransfer
    {
        return DB::transaction(function () use ($actor, $attributes): AccountTransfer {
            $from = $this->account((int) $attributes['from_financial_account_id']);
            $to = $this->account((int) $attributes['to_financial_account_id']);

            if ($from->id === $to->id) {
                throw new ApprovalStateException('Choose two different financial accounts.');
            }

            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The amount must be greater than zero.');
            }

            $transfer = AccountTransfer::query()->create([
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'amount' => $amount,
                'transaction_date' => $attributes['transaction_date'],
                'reference' => Sequence::next(AccountTransfer::class, 'reference', 'AT'),
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit(
                $transfer,
                ApprovalRequestType::AccountTransfer,
                $amount,
                $actor,
                $transfer->note,
            );

            $this->audit->record(AuditAction::Created, $transfer, null, [
                'reference' => $transfer->reference,
                'amount' => $amount,
                'status' => $transfer->status->value,
            ], $actor);

            return $transfer->load('approvalRequest', 'fromAccount', 'toAccount');
        });
    }

    public function cancel(AccountTransfer $transfer, User $actor, ?string $comment = null): AccountTransfer
    {
        $request = $transfer->approvalRequest()->firstOrFail();
        $this->approvals->cancel($request, $actor, $comment);

        return $transfer->fresh();
    }

    private function account(int $id): FinancialAccount
    {
        $account = FinancialAccount::query()->find($id);

        if (! $account instanceof FinancialAccount || ! $account->is_active) {
            throw new ApprovalStateException('The financial account is not available.');
        }

        return $account;
    }
}
