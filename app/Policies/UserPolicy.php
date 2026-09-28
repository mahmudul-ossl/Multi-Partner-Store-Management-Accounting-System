<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UserManage->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UserManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserManage->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserManage->value) && ! $user->is($model);
    }
}
