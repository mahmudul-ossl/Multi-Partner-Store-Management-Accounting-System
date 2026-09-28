<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ApprovalThreshold;
use App\Models\User;

class ApprovalThresholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }

    public function update(User $user, ApprovalThreshold $threshold): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }

    public function delete(User $user, ApprovalThreshold $threshold): bool
    {
        return $user->can(PermissionName::SettingsManage->value);
    }
}
