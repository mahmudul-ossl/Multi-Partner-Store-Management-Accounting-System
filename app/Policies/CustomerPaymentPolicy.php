<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::SaleCreate->value);
    }
}
