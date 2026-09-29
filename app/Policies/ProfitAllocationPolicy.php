<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ProfitAllocation;
use App\Models\User;

class ProfitAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, ProfitAllocation $allocation): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }
}
