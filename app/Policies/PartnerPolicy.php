<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerDirectory;

class PartnerPolicy
{
    public function __construct(private readonly PartnerDirectory $directory) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PartnerView->value);
    }

    public function view(User $user, Partner $partner): bool
    {
        if (! $user->can(PermissionName::PartnerView->value)) {
            return false;
        }

        if (! $this->directory->restrictsToOwnRecord($user)) {
            return true;
        }

        return $partner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PartnerCreate->value);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->can(PermissionName::PartnerUpdate->value) && $this->view($user, $partner);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->can(PermissionName::PartnerDelete->value) && $this->view($user, $partner);
    }
}
