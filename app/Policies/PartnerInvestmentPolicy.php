<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\PartnerInvestment;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Auth\Access\Response;

class PartnerInvestmentPolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PartnerInvestmentView->value);
    }

    public function view(User $user, PartnerInvestment $investment): bool
    {
        if (! $user->can(PermissionName::PartnerInvestmentView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return (int) $investment->partner_id === (int) $user->partner?->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PartnerInvestmentCreate->value);
    }

    public function update(User $user, PartnerInvestment $investment): Response
    {
        if (! $this->view($user, $investment) || ! $user->can(PermissionName::PartnerInvestmentCreate->value)) {
            return Response::deny();
        }

        if (! $investment->isEditable()) {
            return Response::deny('Approved records cannot be edited.');
        }

        return Response::allow();
    }

    public function cancel(User $user, PartnerInvestment $investment): bool
    {
        if ($investment->status !== DocumentStatus::Pending) {
            return false;
        }

        if ((int) $investment->created_by === (int) $user->id) {
            return $this->view($user, $investment);
        }

        return $user->can(PermissionName::PartnerInvestmentApprove->value);
    }

    public function reverse(User $user, PartnerInvestment $investment): bool
    {
        return $investment->status === DocumentStatus::Approved
            && $user->can(PermissionName::PartnerInvestmentApprove->value)
            && (int) $investment->created_by !== (int) $user->id;
    }
}
