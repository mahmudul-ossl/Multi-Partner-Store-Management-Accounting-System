<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class RefundService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{sale_id: int, financial_account_id: int, payment_method: string, amount: string|int, transaction_date: string, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): Refund
    {
        return DB::transaction(function () use ($actor, $attributes): Refund {
            $sale = Sale::query()->lockForUpdate()->findOrFail($attributes['sale_id']);

            if ($sale->status !== DocumentStatus::Completed) {
                throw new ApprovalStateException('Only a completed sale can be refunded.');
            }

            $amount = Money::of($attributes['amount'])->amount();
            $credit = Money::of((string) $sale->due_amount)->compare('0.00') === -1
                ? Money::of('0')->sub((string) $sale->due_amount)->amount()
                : '0.00';

            if (Money::of($amount)->compare('0.00') !== 1 || Money::of($amount)->compare($credit) === 1) {
                throw new ApprovalStateException('The refund cannot exceed the customer credit.');
            }

            $method = PaymentMethod::from($attributes['payment_method']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $this->guard->assertAccount($account, $method);

            $refund = Refund::query()->create([
                'reference' => Sequence::next(Refund::class, 'reference', 'REF'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'financial_account_id' => $account->id,
                'payment_method' => $method,
                'amount' => $amount,
                'transaction_date' => $attributes['transaction_date'],
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($refund, ApprovalRequestType::Refund, $amount, $actor, $refund->note);
            $this->audit->record(AuditAction::Created, $refund, null, [
                'reference' => $refund->reference,
                'amount' => $amount,
            ], $actor);

            return $refund->load('approvalRequest');
        });
    }
}
