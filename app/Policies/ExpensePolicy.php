<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Expense;
use App\Models\User;
use App\Services\PartnerDirectory;

class ExpensePolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ExpenseView->value);
    }

    public function view(User $user, Expense $expense): bool
    {
        if (! $user->can(PermissionName::ExpenseView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return (int) $expense->partner_id === (int) $user->partner?->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ExpenseCreate->value);
    }
}
