<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\AccountTransfer;
use App\Models\User;

class AccountTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, AccountTransfer $transfer): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }

    public function cancel(User $user, AccountTransfer $transfer): bool
    {
        if ($transfer->status !== DocumentStatus::Pending) {
            return false;
        }

        if ((int) $transfer->created_by === (int) $user->id) {
            return $user->can(PermissionName::AccountingView->value);
        }

        return $user->can(PermissionName::AccountingManage->value);
    }
}
