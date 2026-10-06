<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\RoleName;
use App\Exceptions\SelfApprovalException;
use App\Models\User;

/**
 * Later approval services must call this before recording an approval action.
 * The rule is enforced here, not in the UI. Super Admin may approve their own work.
 */
final class SelfApprovalGuard
{
    public static function assertNotSelf(int $creatorUserId, int $actorUserId, ?User $actor = null): void
    {
        if ($creatorUserId !== $actorUserId) {
            return;
        }

        if ($actor?->hasRole(RoleName::SuperAdmin->value)) {
            return;
        }

        throw new SelfApprovalException('You cannot approve your own transaction.');
    }
}
