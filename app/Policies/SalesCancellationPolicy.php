<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class SalesCancellationPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::SaleCreate->value);
    }
}
