<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class SupplierPaymentService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{supplier_id: int, purchase_id?: int|null, financial_account_id: int, payment_method: string, amount: string|int, payment_date: string, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): SupplierPayment
    {
        return DB::transaction(function () use ($actor, $attributes): SupplierPayment {
            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The amount must be greater than zero.');
            }

            $purchase = null;

            if (! empty($attributes['purchase_id'])) {
                $purchase = Purchase::query()->lockForUpdate()->findOrFail($attributes['purchase_id']);

                if ($purchase->status !== DocumentStatus::Approved) {
                    throw new ApprovalStateException('Payments apply to an approved purchase.');
                }

                if ((int) $purchase->supplier_id !== (int) $attributes['supplier_id']) {
                    throw new ApprovalStateException('That purchase belongs to another supplier.');
                }

                if (Money::of($amount)->compare((string) $purchase->due_amount) === 1) {
                    throw new ApprovalStateException('The payment is larger than the purchase due.');
                }
            }

            $method = PaymentMethod::from($attributes['payment_method']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $this->guard->assertAccount($account, $method);

            $payment = SupplierPayment::query()->create([
                'reference' => Sequence::next(SupplierPayment::class, 'reference', 'PAY'),
                'supplier_id' => $attributes['supplier_id'],
                'purchase_id' => $purchase?->id,
                'financial_account_id' => $account->id,
                'payment_method' => $method,
                'amount' => $amount,
                'payment_date' => $attributes['payment_date'],
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($payment, ApprovalRequestType::Purchase, $amount, $actor, $payment->note);
            $this->audit->record(AuditAction::Created, $payment, null, ['reference' => $payment->reference, 'amount' => $amount], $actor);

            return $payment->load('approvalRequest');
        });
    }
}
