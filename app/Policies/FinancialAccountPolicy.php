<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }

    public function reconcile(User $user, FinancialAccount $account): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }
}
