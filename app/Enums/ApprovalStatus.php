<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of an approval request. Kept aligned with DocumentStatus so the
 * approval workflow and the document it gates share one vocabulary.
 */
enum ApprovalStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
