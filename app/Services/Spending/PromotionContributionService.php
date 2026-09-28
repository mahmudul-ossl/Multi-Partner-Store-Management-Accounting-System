<?php

declare(strict_types=1);

namespace App\Services\Spending;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\FundingSource;
use App\Enums\PartnerStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\Promotion;
use App\Models\PromotionPartnerExpense;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class PromotionContributionService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
    ) {}

    /**
     * @param  array{partner_id: int, funded_by: string, amount: string|int, transaction_date: string, payment_method?: string|null, financial_account_id?: int|null, note?: string|null}  $attributes
     */
    public function create(User $actor, Promotion $promotion, array $attributes): PromotionPartnerExpense
    {
        return DB::transaction(function () use ($actor, $promotion, $attributes): PromotionPartnerExpense {
            $promotion = Promotion::query()->whereKey($promotion->id)->lockForUpdate()->firstOrFail();

            if (! $promotion->status->acceptsContributions()) {
                throw new ApprovalStateException('Contributions can only be added to a planned or active promotion.');
            }

            $partner = Partner::query()->where('status', PartnerStatus::Active)->findOrFail($attributes['partner_id']);
            $this->guard->assertOwnPartner($actor, $partner->id);

            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The contribution must be greater than zero.');
            }

            $funded = FundingSource::from($attributes['funded_by']);
            $method = null;
            $accountId = null;

            if ($funded === FundingSource::Business) {
                if (empty($attributes['payment_method']) || empty($attributes['financial_account_id'])) {
                    throw new ApprovalStateException('A business-paid contribution needs a method and a financial account.');
                }

                $method = PaymentMethod::from($attributes['payment_method']);
                $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
                $this->guard->assertAccount($account, $method);
                $accountId = $account->id;
            } elseif (! empty($attributes['financial_account_id'])) {
                throw new ApprovalStateException('A partner-paid contribution does not use a business account.');
            }

            $contribution = PromotionPartnerExpense::query()->create([
                'reference' => Sequence::next(PromotionPartnerExpense::class, 'reference', 'PPC'),
                'promotion_id' => $promotion->id,
                'partner_id' => $partner->id,
                'amount' => $amount,
                'funded_by' => $funded,
                'payment_method' => $method,
                'financial_account_id' => $accountId,
                'transaction_date' => $attributes['transaction_date'],
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($contribution, ApprovalRequestType::PromotionExpense, $amount, $actor, $contribution->note);
            $this->audit->record(AuditAction::Created, $contribution, null, [
                'reference' => $contribution->reference,
                'amount' => $amount,
                'funded_by' => $funded->value,
            ], $actor);

            return $contribution->load('approvalRequest');
        });
    }
}
