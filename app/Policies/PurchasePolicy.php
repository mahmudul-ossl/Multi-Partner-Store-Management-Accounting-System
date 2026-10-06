<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PurchaseView->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->can(PermissionName::PurchaseView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PurchaseCreate->value);
    }

    public function update(User $user, Purchase $purchase): Response
    {
        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            return Response::deny('Only a Super Admin can edit purchases.');
        }

        if (! in_array($purchase->status, [DocumentStatus::Pending, DocumentStatus::Approved], true)) {
            return Response::deny('This purchase cannot be edited.');
        }

        return Response::allow();
    }
}
