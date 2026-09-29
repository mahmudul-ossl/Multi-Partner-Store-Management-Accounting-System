<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PurchaseView->value);
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $user->can(PermissionName::PurchaseView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PurchaseCreate->value);
    }
}
