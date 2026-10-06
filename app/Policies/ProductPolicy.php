<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProductView->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProductManage->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductManage->value);
    }
}
