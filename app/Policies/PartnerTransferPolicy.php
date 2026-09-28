<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\PartnerTransfer;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Auth\Access\Response;

class PartnerTransferPolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PartnerTransferView->value);
    }

    public function view(User $user, PartnerTransfer $transfer): bool
    {
        if (! $user->can(PermissionName::PartnerTransferView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        $own = (int) $user->partner?->id;

        return $own !== 0 && ($transfer->from_partner_id === $own || $transfer->to_partner_id === $own);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PartnerTransferCreate->value);
    }

    public function update(User $user, PartnerTransfer $transfer): Response
    {
        if (! $this->view($user, $transfer) || ! $user->can(PermissionName::PartnerTransferCreate->value)) {
            return Response::deny();
        }

        if (! $transfer->isEditable()) {
            return Response::deny('Approved records cannot be edited.');
        }

        return Response::allow();
    }

    public function cancel(User $user, PartnerTransfer $transfer): bool
    {
        if ($transfer->status !== DocumentStatus::Pending) {
            return false;
        }

        if ((int) $transfer->created_by === (int) $user->id) {
            return $this->view($user, $transfer);
        }

        return $user->can(PermissionName::PartnerTransferApprove->value);
    }

    public function reverse(User $user, PartnerTransfer $transfer): bool
    {
        return $transfer->status === DocumentStatus::Approved
            && $user->can(PermissionName::PartnerTransferApprove->value)
            && (int) $transfer->created_by !== (int) $user->id;
    }
}
