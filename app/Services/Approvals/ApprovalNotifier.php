<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Notifications\ApprovalActivity;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ApprovalNotifier
{
    public function requested(ApprovalRequest $request): void
    {
        $request->loadMissing('reference', 'requester');
        $permission = $request->request_type->approvePermission()->value;

        $recipients = User::permission($permission)
            ->where('is_active', true)
            ->where('id', '!=', $request->requested_by)
            ->get();

        $this->send(
            $recipients,
            'New '.$request->request_type->label().' approval required',
            $this->sentence($request, 'approval required'),
            $request,
        );
    }

    public function decided(ApprovalRequest $request, string $decision): void
    {
        $request->loadMissing('reference', 'requester');

        if (! $request->requester instanceof User) {
            return;
        }

        $this->send(
            [$request->requester],
            $request->request_type->label().' '.$decision,
            $this->sentence($request, $decision),
            $request,
        );
    }

    /**
     * @param  iterable<User>  $recipients
     */
    private function send(iterable $recipients, string $title, string $message, ApprovalRequest $request): void
    {
        $notification = new ApprovalActivity($title, $message, route('approvals.show', $request));

        DB::afterCommit(function () use ($recipients, $notification): void {
            Notification::send($recipients, $notification);
        });
    }

    private function sentence(ApprovalRequest $request, string $decision): string
    {
        $document = $request->reference;
        $date = $document->transaction_date ?? $document->payment_date ?? $document->entry_date ?? $request->requested_at;

        return sprintf(
            'New %s %s — Amount %s, Partner %s, Date %s',
            strtolower($request->request_type->label()),
            $decision,
            Money::of((string) $request->amount)->formatted(),
            $this->partnerName($request),
            Format::date($date),
        );
    }

    private function partnerName(ApprovalRequest $request): string
    {
        $document = $request->reference;

        return match (true) {
            $document instanceof PartnerInvestment, $document instanceof PartnerWithdrawal => $document->loadMissing('partner')->partner?->name ?? '—',
            $document instanceof PartnerTransfer => ($document->loadMissing('fromPartner', 'toPartner')->fromPartner?->name ?? '—').' to '.($document->toPartner?->name ?? '—'),
            $document instanceof Purchase, $document instanceof PurchaseReturn, $document instanceof SupplierPayment => $document->loadMissing('supplier')->supplier?->name ?? '—',
            $document instanceof StockAdjustment => $document->loadMissing('product')->product?->name ?? '—',
            default => '—',
        };
    }
}
