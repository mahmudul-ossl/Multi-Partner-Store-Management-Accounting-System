<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesIncomeSource;
use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\SalesIncome;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class SalesIncomeService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{source: string, amount: string|int, transaction_date: string, payment_method: string, financial_account_id: int, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): SalesIncome
    {
        return DB::transaction(function () use ($actor, $attributes): SalesIncome {
            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The sale amount must be greater than zero.');
            }

            $method = PaymentMethod::from($attributes['payment_method']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $this->guard->assertAccount($account, $method);

            $income = SalesIncome::query()->create([
                'reference' => Sequence::next(SalesIncome::class, 'reference', 'SIN'),
                'source' => SalesIncomeSource::from($attributes['source']),
                'amount' => $amount,
                'transaction_date' => $attributes['transaction_date'],
                'payment_method' => $method,
                'financial_account_id' => $account->id,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($income, ApprovalRequestType::SalesIncome, $amount, $actor, $income->note);
            $this->audit->record(AuditAction::Created, $income, null, [
                'reference' => $income->reference,
                'amount' => $amount,
                'source' => $income->source->value,
            ], $actor);

            return $income->load('approvalRequest');
        });
    }
}
