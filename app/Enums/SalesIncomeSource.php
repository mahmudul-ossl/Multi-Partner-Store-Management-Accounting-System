<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesIncomeSource: string
{
    case Website = 'website';
    case Shop = 'shop';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Shop => 'Shop',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $source): array => ['value' => $source->value, 'label' => $source->label()],
            self::cases(),
        );
    }
}
