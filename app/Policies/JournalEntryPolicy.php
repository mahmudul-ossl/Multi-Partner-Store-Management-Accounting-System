<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->can(PermissionName::AccountingManage->value)
            && $entry->status === DocumentStatus::Completed
            && $entry->reversal_of === null;
    }
}
