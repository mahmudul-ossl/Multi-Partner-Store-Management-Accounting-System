<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
    case Payment = 'payment';
    case StockAdjusted = 'stock_adjusted';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Reversed => 'Reversed',
            self::Payment => 'Payment',
            self::StockAdjusted => 'Stock adjustment',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Login, self::Approved => 'green',
            self::Logout, self::Cancelled, self::Reversed => 'slate',
            self::Created => 'blue',
            self::Updated => 'amber',
            self::Deleted, self::Rejected => 'red',
            self::Payment, self::StockAdjusted => 'blue',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $action): array => [
                'value' => $action->value,
                'label' => $action->label(),
            ],
            self::cases(),
        );
    }
}
