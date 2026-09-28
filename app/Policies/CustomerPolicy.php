<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CustomerView->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CustomerManage->value);
    }
}
