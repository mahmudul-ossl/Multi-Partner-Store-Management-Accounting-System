<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ChartOfAccount;
use App\Models\User;

class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }
}
