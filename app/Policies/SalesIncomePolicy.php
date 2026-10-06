<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SalesIncome;
use App\Models\User;

class SalesIncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SaleView->value);
    }

    public function view(User $user, SalesIncome $salesIncome): bool
    {
        return $user->can(PermissionName::SaleView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SaleCreate->value);
    }
}
