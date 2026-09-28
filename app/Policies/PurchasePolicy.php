<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Purchase;
use App\Models\User;

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
}
