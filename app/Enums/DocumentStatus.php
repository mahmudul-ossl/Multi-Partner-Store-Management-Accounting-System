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

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
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
}
