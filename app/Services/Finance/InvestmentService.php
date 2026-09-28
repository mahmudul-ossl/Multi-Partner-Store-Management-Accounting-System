<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ImmutableDocumentException;
use App\Models\FinancialAccount;
use App\Models\PartnerInvestment;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Ledger\PartnerFinancePoster;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class InvestmentService
{
    public function __construct(
        private readonly PartnerFinanceGuard $guard,
        private readonly ApprovalService $approvals,
        private readonly PartnerFinancePoster $poster,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{partner_id: int, amount: string|int, transaction_date: string, payment_method: string, financial_account_id: int, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): PartnerInvestment
    {
        return DB::transaction(function () use ($actor, $attributes): PartnerInvestment {
            $this->guard->assertOwnPartner($actor, (int) $attributes['partner_id']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $method = PaymentMethod::from($attributes['payment_method']);
            $this->guard->assertAccount($account, $method);

            $investment = PartnerInvestment::query()->create([
                'partner_id' => $attributes['partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'payment_method' => $method,
                'financial_account_id' => $account->id,
                'reference' => Sequence::next(PartnerInvestment::class, 'reference', 'INV'),
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit(
                $investment,
                ApprovalRequestType::Investment,
                (string) $investment->amount,
                $actor,
                $investment->note,
            );

            $this->audit->record(AuditAction::Created, $investment, null, $this->snapshot($investment), $actor);

            return $investment->load('approvalRequest');
        });
    }

    /**
     * @param  array{partner_id: int, amount: string|int, transaction_date: string, payment_method: string, financial_account_id: int, note?: string|null}  $attributes
     */
    public function update(PartnerInvestment $investment, User $actor, array $attributes): PartnerInvestment
    {
        return DB::transaction(function () use ($investment, $actor, $attributes): PartnerInvestment {
            $investment = PartnerInvestment::query()->whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if (! $investment->isEditable()) {
                throw new ImmutableDocumentException('Approved records cannot be edited.');
            }

            $this->guard->assertOwnPartner($actor, (int) $attributes['partner_id']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $method = PaymentMethod::from($attributes['payment_method']);
            $this->guard->assertAccount($account, $method);

            $before = $this->snapshot($investment);
            $investment->fill([
                'partner_id' => $attributes['partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'payment_method' => $method,
                'financial_account_id' => $account->id,
                'note' => $attributes['note'] ?? null,
            ]);
            $investment->save();

            $this->approvals->syncAmount($investment, ApprovalRequestType::Investment, (string) $investment->amount);
            $this->audit->record(AuditAction::Updated, $investment, $before, $this->snapshot($investment), $actor);

            return $investment;
        });
    }

    public function cancel(PartnerInvestment $investment, User $actor, ?string $comment = null): PartnerInvestment
    {
        $request = $investment->approvalRequest()->firstOrFail();
        $this->approvals->cancel($request, $actor, $comment);

        return $investment->fresh();
    }

    public function reverse(PartnerInvestment $investment, User $actor, string $reason): PartnerInvestment
    {
        return DB::transaction(function () use ($investment, $actor, $reason): PartnerInvestment {
            $investment = PartnerInvestment::query()->whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($investment->status !== DocumentStatus::Approved) {
                throw new ImmutableDocumentException('Only an approved investment can be reversed.');
            }

            $this->poster->reverse($investment, $actor, $reason);
            $investment->status = DocumentStatus::Reversed;
            $investment->save();

            $this->audit->record(AuditAction::Reversed, $investment, null, [
                'status' => DocumentStatus::Reversed->value,
                'reason' => $reason,
            ], $actor);

            return $investment;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PartnerInvestment $investment): array
    {
        return [
            'reference' => $investment->reference,
            'partner_id' => $investment->partner_id,
            'amount' => (string) $investment->amount,
            'transaction_date' => $investment->transaction_date?->toDateString(),
            'payment_method' => $investment->payment_method->value,
            'financial_account_id' => $investment->financial_account_id,
            'status' => $investment->status->value,
            'note' => $investment->note,
        ];
    }
}
