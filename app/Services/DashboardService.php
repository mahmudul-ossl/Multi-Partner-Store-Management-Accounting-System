<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PartnerStatus;
use App\Enums\PermissionName;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\User;
use App\Services\Approvals\ApprovalDirectory;
use App\Support\Format;
use App\Support\Money;

final class DashboardService
{
    public function __construct(
        private readonly PartnerDirectory $partners,
        private readonly ApprovalDirectory $approvals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $actor): array
    {
        $visible = $this->partners->visibleTo($actor);
        $canSeeUsers = $actor->can(PermissionName::UserManage->value);
        $canSeeCash = $actor->can(PermissionName::AccountingView->value);
        $canSeeApprovals = $actor->can(PermissionName::ApprovalView->value)
            || $actor->can(PermissionName::PartnerInvestmentApprove->value)
            || $actor->can(PermissionName::PartnerWithdrawalApprove->value)
            || $actor->can(PermissionName::PartnerTransferApprove->value);

        return [
            'partners_total' => (clone $visible)->count(),
            'partners_active' => (clone $visible)->where('status', PartnerStatus::Active)->count(),
            'partners_inactive' => (clone $visible)->where('status', PartnerStatus::Inactive)->count(),
            'partners_suspended' => (clone $visible)->where('status', PartnerStatus::Suspended)->count(),
            'show_user_counts' => $canSeeUsers,
            'users_total' => $canSeeUsers ? User::query()->count() : null,
            'users_active' => $canSeeUsers ? User::query()->where('is_active', true)->count() : null,
            'recent_partners' => (clone $visible)
                ->latest('joining_date')
                ->limit(5)
                ->get(['id', 'partner_code', 'name', 'status', 'joining_date'])
                ->map(fn (Partner $partner): array => [
                    'id' => $partner->id,
                    'partner_code' => $partner->partner_code,
                    'name' => $partner->name,
                    'status' => [
                        'value' => $partner->status->value,
                        'label' => $partner->status->label(),
                        'tone' => $partner->status->tone(),
                    ],
                    'joining_date' => Format::date($partner->joining_date),
                ])
                ->all(),
            'show_cash' => $canSeeCash,
            'cash_and_bank' => $canSeeCash ? $this->cashAndBank() : null,
            'show_pending_approvals' => $canSeeApprovals,
            'pending_approvals' => $canSeeApprovals ? $this->approvals->countActionable($actor) : null,
            'placeholders' => [
                ['key' => 'inventory_value', 'label' => 'Inventory value', 'note' => 'Coming in a later phase'],
                ['key' => 'sales', 'label' => 'Sales', 'note' => 'Coming in a later phase'],
            ],
        ];
    }

    private function cashAndBank(): string
    {
        $total = '0.00';

        foreach (FinancialAccount::query()->where('is_active', true)->pluck('current_balance') as $balance) {
            $total = Money::of($total)->add((string) $balance)->amount();
        }

        return Money::of($total)->formatted();
    }
}
