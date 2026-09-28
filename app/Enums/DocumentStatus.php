<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shared lifecycle for financial and operational documents.
 * Later phases (investments, withdrawals, purchases, stock adjustments)
 * must use this vocabulary. Records in these states are voided or reversed,
 * never hard-deleted.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
