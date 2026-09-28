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

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Login => 'green',
            self::Logout => 'slate',
            self::Created => 'blue',
            self::Updated => 'amber',
            self::Deleted => 'red',
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
