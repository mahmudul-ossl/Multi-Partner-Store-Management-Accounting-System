<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Auth\Access\Response;

class PartnerWithdrawalPolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PartnerWithdrawalView->value);
    }

    public function view(User $user, PartnerWithdrawal $withdrawal): bool
    {
        if (! $user->can(PermissionName::PartnerWithdrawalView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return (int) $withdrawal->partner_id === (int) $user->partner?->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PartnerWithdrawalCreate->value);
    }

    public function update(User $user, PartnerWithdrawal $withdrawal): Response
    {
        if (! $this->view($user, $withdrawal) || ! $user->can(PermissionName::PartnerWithdrawalCreate->value)) {
            return Response::deny();
        }

        if (! $withdrawal->isEditable()) {
            return Response::deny('Approved records cannot be edited.');
        }

        return Response::allow();
    }

    public function cancel(User $user, PartnerWithdrawal $withdrawal): bool
    {
        if ($withdrawal->status !== DocumentStatus::Pending) {
            return false;
        }

        if ((int) $withdrawal->created_by === (int) $user->id) {
            return $this->view($user, $withdrawal);
        }

        return $user->can(PermissionName::PartnerWithdrawalApprove->value);
    }

    public function reverse(User $user, PartnerWithdrawal $withdrawal): bool
    {
        return $withdrawal->status === DocumentStatus::Approved
            && $user->can(PermissionName::PartnerWithdrawalApprove->value)
            && (int) $withdrawal->created_by !== (int) $user->id;
    }
}
