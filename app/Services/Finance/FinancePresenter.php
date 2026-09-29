<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ApprovalActionType;
use App\Enums\DocumentStatus;
use App\Models\ApprovalRequest;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Support\Format;
use App\Support\Money;

final class FinancePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function investment(PartnerInvestment $investment): array
    {
        $investment->loadMissing('partner', 'financialAccount', 'author', 'approvalRequest');

        return [
            ...$this->common($investment->reference, (string) $investment->amount, $investment->transaction_date, $investment->status, $investment->note, $investment->approvalRequest, $investment->created_at),
            'id' => $investment->id,
            'partner' => $this->partner($investment->partner),
            'partner_id' => $investment->partner_id,
            'payment_method' => $investment->payment_method->value,
            'financial_account_id' => $investment->financial_account_id,
            'financial_account' => $investment->financialAccount?->name,
            'created_by' => $investment->author?->name,
            'editable' => $investment->isEditable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function withdrawal(PartnerWithdrawal $withdrawal): array
    {
        $withdrawal->loadMissing('partner', 'financialAccount', 'author', 'approvalRequest');

        return [
            ...$this->common($withdrawal->reference, (string) $withdrawal->amount, $withdrawal->transaction_date, $withdrawal->status, $withdrawal->note, $withdrawal->approvalRequest, $withdrawal->created_at),
            'id' => $withdrawal->id,
            'partner' => $this->partner($withdrawal->partner),
            'partner_id' => $withdrawal->partner_id,
            'reason' => $withdrawal->reason,
            'payment_method' => $withdrawal->payment_method->value,
            'financial_account_id' => $withdrawal->financial_account_id,
            'financial_account' => $withdrawal->financialAccount?->name,
            'created_by' => $withdrawal->author?->name,
            'editable' => $withdrawal->isEditable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transfer(PartnerTransfer $transfer): array
    {
        $transfer->loadMissing('fromPartner', 'toPartner', 'author', 'approvalRequest');

        return [
            ...$this->common($transfer->reference, (string) $transfer->amount, $transfer->transaction_date, $transfer->status, $transfer->note, $transfer->approvalRequest, $transfer->created_at),
            'id' => $transfer->id,
            'from_partner' => $this->partner($transfer->fromPartner),
            'to_partner' => $this->partner($transfer->toPartner),
            'from_partner_id' => $transfer->from_partner_id,
            'to_partner_id' => $transfer->to_partner_id,
            'created_by' => $transfer->author?->name,
            'editable' => $transfer->isEditable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approval(ApprovalRequest $request, User $actor): array
    {
        $request->loadMissing('requester', 'actions.user', 'reference');
        $document = $request->reference;
        $summary = match (true) {
            $document instanceof PartnerInvestment => $this->investment($document),
            $document instanceof PartnerWithdrawal => $this->withdrawal($document),
            $document instanceof PartnerTransfer => $this->transfer($document),
            default => null,
        };

        return [
            'id' => $request->id,
            'type' => [
                'value' => $request->request_type->value,
                'label' => $request->request_type->label(),
            ],
            'status' => [
                'value' => $request->status->value,
                'label' => $request->status->label(),
                'tone' => $request->status->tone(),
            ],
            'amount' => (string) $request->amount,
            'amount_formatted' => Money::of((string) $request->amount)->formatted(),
            'required_approvals' => $request->required_approvals,
            'completed_approvals' => $request->completed_approvals,
            'requested_at' => Format::dateTime($request->requested_at),
            'note' => $request->note,
            'requester' => $request->requester?->name,
            'partner_label' => $this->partnerLabel($summary),
            'document' => $summary,
            'can_decide' => $request->status->isOpen()
                && (int) $request->requested_by !== (int) $actor->id
                && $actor->can($request->request_type->approvePermission()->value)
                && ! $request->actions->contains(fn ($action): bool => (int) $action->user_id === (int) $actor->id && $action->action === ApprovalActionType::Approved),
            'actions' => $request->actions->map(fn ($action): array => [
                'id' => $action->id,
                'action' => $action->action->label(),
                'comment' => $action->comment,
                'user' => $action->user?->name,
                'created_at' => Format::dateTime($action->created_at),
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, partner_code: string}|null
     */
    private function partner(?Partner $partner): ?array
    {
        if ($partner === null) {
            return null;
        }

        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'partner_code' => $partner->partner_code,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    private function partnerLabel(?array $summary): string
    {
        if ($summary === null) {
            return '—';
        }

        if (isset($summary['partner']['name'])) {
            return $summary['partner']['name'];
        }

        return ($summary['from_partner']['name'] ?? '—').' → '.($summary['to_partner']['name'] ?? '—');
    }

    /**
     * @return array<string, mixed>
     */
    private function common(string $reference, string $amount, mixed $date, DocumentStatus $status, ?string $note, ?ApprovalRequest $approval, mixed $createdAt): array
    {
        return [
            'reference' => $reference,
            'amount' => $amount,
            'amount_formatted' => Money::of($amount)->formatted(),
            'transaction_date' => $date?->toDateString(),
            'transaction_date_formatted' => Format::date($date),
            'status' => [
                'value' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
            ],
            'note' => $note,
            'approval' => $approval === null ? null : [
                'id' => $approval->id,
                'status' => $approval->status->label(),
                'required_approvals' => $approval->required_approvals,
                'completed_approvals' => $approval->completed_approvals,
            ],
            'created_at' => Format::dateTime($createdAt),
        ];
    }
}
