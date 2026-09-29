<?php

declare(strict_types=1);

namespace App\Services\Spending;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\ExpenseCategory;
use App\Enums\PartnerStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class ExpenseService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{category: string, amount: string|int, transaction_date: string, description: string, partner_id?: int|null, payment_method?: string|null, financial_account_id?: int|null}  $attributes
     */
    public function create(User $actor, array $attributes): Expense
    {
        return DB::transaction(function () use ($actor, $attributes): Expense {
            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The expense must be greater than zero.');
            }

            $description = trim($attributes['description']);

            if ($description === '') {
                throw new ApprovalStateException('An expense needs a description.');
            }

            $partnerId = $attributes['partner_id'] ?? null;
            $method = null;
            $accountId = null;

            if ($partnerId) {
                $partner = Partner::query()->where('status', PartnerStatus::Active)->findOrFail($partnerId);
                $this->guard->assertOwnPartner($actor, $partner->id);
                $partnerId = $partner->id;

                if (! empty($attributes['financial_account_id'])) {
                    throw new ApprovalStateException('A partner-paid expense does not use a business account.');
                }
            } else {
                if (empty($attributes['payment_method']) || empty($attributes['financial_account_id'])) {
                    throw new ApprovalStateException('A business expense needs a method and a financial account.');
                }

                $method = PaymentMethod::from($attributes['payment_method']);
                $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
                $this->guard->assertAccount($account, $method);
                $accountId = $account->id;
                $partnerId = null;
            }

            $expense = Expense::query()->create([
                'reference' => Sequence::next(Expense::class, 'reference', 'EXP'),
                'category' => ExpenseCategory::from($attributes['category']),
                'amount' => $amount,
                'transaction_date' => $attributes['transaction_date'],
                'payment_method' => $method,
                'financial_account_id' => $accountId,
                'partner_id' => $partnerId,
                'description' => $description,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($expense, ApprovalRequestType::PartnerExpense, $amount, $actor, $expense->description);
            $this->audit->record(AuditAction::Created, $expense, null, [
                'reference' => $expense->reference,
                'amount' => $amount,
                'category' => $expense->category->value,
            ], $actor);

            return $expense->load('approvalRequest');
        });
    }
}
