<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApprovalActionType;
use App\Enums\PermissionName;
use App\Models\ApprovalRequest;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Auth\Access\Response;

class ApprovalRequestPolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ApprovalView->value)
            || $user->can(PermissionName::PartnerInvestmentApprove->value)
            || $user->can(PermissionName::PartnerWithdrawalApprove->value)
            || $user->can(PermissionName::PartnerTransferApprove->value);
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        if ($user->can($request->request_type->approvePermission()->value)) {
            return true;
        }

        if (! $user->can(PermissionName::ApprovalView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return $this->involvesOwnPartner($user, $request);
    }

    public function approve(User $user, ApprovalRequest $request): Response
    {
        if ((int) $request->requested_by === (int) $user->id) {
            return Response::deny('You cannot approve your own transaction.');
        }

        if (! $user->can($request->request_type->approvePermission()->value)) {
            return Response::deny('You do not have permission to decide this request.');
        }

        if (! $request->status->isOpen()) {
            return Response::deny('This request is no longer waiting for approval.');
        }

        $already = $request->actions()
            ->where('user_id', $user->id)
            ->where('action', ApprovalActionType::Approved)
            ->exists();

        if ($already) {
            return Response::deny('You have already approved this request.');
        }

        return Response::allow();
    }

    public function reject(User $user, ApprovalRequest $request): Response
    {
        if ((int) $request->requested_by === (int) $user->id) {
            return Response::deny('You cannot reject your own transaction.');
        }

        if (! $user->can($request->request_type->approvePermission()->value)) {
            return Response::deny('You do not have permission to decide this request.');
        }

        if (! $request->status->isOpen()) {
            return Response::deny('This request is no longer waiting for approval.');
        }

        return Response::allow();
    }

    private function involvesOwnPartner(User $user, ApprovalRequest $request): bool
    {
        $own = (int) $user->partner?->id;
        $document = $request->reference;

        return match (true) {
            $document instanceof PartnerInvestment, $document instanceof PartnerWithdrawal => (int) $document->partner_id === $own,
            $document instanceof PartnerTransfer => (int) $document->from_partner_id === $own || (int) $document->to_partner_id === $own,
            default => false,
        };
    }
}
