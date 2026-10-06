<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Models\ManualJournal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ManualJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function view(User $user, ManualJournal $journal): bool
    {
        return $user->can(PermissionName::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AccountingManage->value);
    }

    public function update(User $user, ManualJournal $journal): Response
    {
        if (! $user->can(PermissionName::AccountingManage->value)) {
            return Response::deny();
        }

        if (! $journal->isEditable()) {
            return Response::deny('Approved records cannot be edited.');
        }

        return Response::allow();
    }

    public function cancel(User $user, ManualJournal $journal): bool
    {
        if ($journal->status !== DocumentStatus::Pending) {
            return false;
        }

        if ((int) $journal->created_by === (int) $user->id) {
            return $user->can(PermissionName::AccountingView->value);
        }

        return $user->can(PermissionName::AccountingManage->value);
    }
}
