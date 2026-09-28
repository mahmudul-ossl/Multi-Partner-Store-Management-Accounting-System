<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PurchaseView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PurchaseCreate->value);
    }
}
