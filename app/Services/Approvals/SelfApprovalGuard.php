<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Exceptions\SelfApprovalException;

/**
 * Later approval services must call this before recording an approval action.
 * The rule is enforced here, not in the UI.
 */
final class SelfApprovalGuard
{
    public static function assertNotSelf(int $creatorUserId, int $actorUserId): void
    {
        if ($creatorUserId === $actorUserId) {
            throw new SelfApprovalException('You cannot approve your own transaction.');
        }
    }
}
