<?php

declare(strict_types=1);

namespace App\Enums;

enum AllocationMethod: string
{
    case Ownership = 'ownership';
    case Investment = 'investment';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Ownership => 'Ownership %',
            self::Investment => 'Investment %',
            self::Custom => 'Custom %',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => ['value' => $method->value, 'label' => $method->label()],
            self::cases(),
        );
    }
}
