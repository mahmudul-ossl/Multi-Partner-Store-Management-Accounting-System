<?php

declare(strict_types=1);

namespace App\Enums;

enum StockAdjustmentKind: string
{
    case Opening = 'opening';
    case Adjustment = 'adjustment';
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Adjustment => 'Adjustment',
            self::Damage => 'Damage',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
