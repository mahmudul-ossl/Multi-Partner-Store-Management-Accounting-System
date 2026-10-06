<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SaleView->value);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can(PermissionName::SaleView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SaleCreate->value);
    }
}
