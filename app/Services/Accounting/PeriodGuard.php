<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ApprovalStateException;
use App\Models\AccountingPeriod;

/**
 * Dates on or before the latest close cannot receive a new journal.
 */
final class PeriodGuard
{
    public function closedThrough(): ?string
    {
        $date = AccountingPeriod::query()->max('closed_through');

        if ($date === null) {
            return null;
        }

        return substr((string) $date, 0, 10);
    }

    public function assertOpen(string $date): void
    {
        $closed = $this->closedThrough();
        $day = substr($date, 0, 10);

        if ($closed !== null && $day <= $closed) {
            throw new ApprovalStateException('That date is in a closed period.');
        }
    }
}
