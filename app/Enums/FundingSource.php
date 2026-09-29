<?php

declare(strict_types=1);

namespace App\Enums;

enum FundingSource: string
{
    case Partner = 'partner';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'Partner paid personally',
            self::Business => 'Business paid',
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
