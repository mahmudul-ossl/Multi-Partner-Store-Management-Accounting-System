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
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::PartiallyApproved => 'Partially approved',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Reversed => 'Reversed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved, self::Completed => 'green',
            self::PartiallyApproved => 'amber',
            self::Pending, self::Draft => 'blue',
            self::Rejected => 'red',
            self::Cancelled, self::Reversed => 'slate',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::PartiallyApproved;
    }
}
