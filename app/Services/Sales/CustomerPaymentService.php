<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\CustomerPayment;
use App\Models\FinancialAccount;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class CustomerPaymentService
{
    public function __construct(
        private readonly JournalEntryService $journal,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{sale_id: int, financial_account_id: int, payment_method: string, amount: string|int, payment_date: string, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): CustomerPayment
    {
        return DB::transaction(function () use ($actor, $attributes): CustomerPayment {
            $sale = Sale::query()->lockForUpdate()->findOrFail($attributes['sale_id']);

            if ($sale->status !== DocumentStatus::Completed) {
                throw new ApprovalStateException('Payments apply to a completed sale.');
            }

            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1 || Money::of($amount)->compare((string) $sale->due_amount) === 1) {
                throw new ApprovalStateException('The payment cannot exceed the amount due.');
            }

            $method = PaymentMethod::from($attributes['payment_method']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $this->guard->assertAccount($account, $method);

            $payment = CustomerPayment::query()->create([
                'reference' => Sequence::next(CustomerPayment::class, 'reference', 'RCP'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'financial_account_id' => $account->id,
                'payment_method' => $method,
                'amount' => $amount,
                'payment_date' => $attributes['payment_date'],
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Completed,
                'created_by' => $actor->id,
            ]);

            $sale->due_amount = Money::of((string) $sale->due_amount)->sub($amount)->amount();
            $sale->paid_amount = Money::of((string) $sale->paid_amount)->add($amount)->amount();
            $sale->save();

            $description = 'Customer payment '.$payment->reference;
            $entry = $this->journal->post($payment, $actor, $payment->payment_date->toDateString(), $description, [
                [
                    'account_code' => $account->chartOfAccount->code,
                    'financial_account_id' => $account->id,
                    'customer_id' => $sale->customer_id,
                    'debit' => $amount,
                    'credit' => '0.00',
                    'description' => $description,
                ],
                [
                    'account_code' => ChartAccountCode::AccountsReceivable,
                    'customer_id' => $sale->customer_id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => $description,
                ],
            ]);
            $payment->journal_entry_id = $entry->id;
            $payment->save();
            $this->audit->record(AuditAction::Created, $payment, null, [
                'reference' => $payment->reference,
                'amount' => $amount,
            ], $actor);

            return $payment;
        });
    }
}
