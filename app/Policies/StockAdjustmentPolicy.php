<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::StockView->value);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(PermissionName::StockView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::StockAdjust->value);
    }
}
