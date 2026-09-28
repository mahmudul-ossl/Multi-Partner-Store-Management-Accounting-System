<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PromotionPartnerExpense;
use App\Models\User;
use App\Services\PartnerDirectory;

class PromotionPartnerExpensePolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PromotionView->value);
    }

    public function view(User $user, PromotionPartnerExpense $contribution): bool
    {
        if (! $user->can(PermissionName::PromotionView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return (int) $contribution->partner_id === (int) $user->partner?->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PromotionCreate->value);
    }
}
