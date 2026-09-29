<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Weighted-average costing. Phase 5 COGS must take the unit cost from
 * InventoryService at the moment stock leaves, which is this average.
 *
 * Quantities use 3 decimal places. Unit costs use 4. Journal amounts are
 * rounded half-up to 2 decimal places so they fit the money columns.
 */
final class Costing
{
    public static function quantity(string|int $quantity): string
    {
        return self::scale($quantity, 3);
    }

    public static function cost(string|int $cost): string
    {
        return self::scale($cost, 4);
    }

    public static function money(string|int $amount): string
    {
        return self::scale($amount, 2);
    }

    public static function lineTotal(string|int $quantity, string|int $unitCost): string
    {
        $raw = bcmul(self::quantity($quantity), self::cost($unitCost), 8);

        return self::round($raw, 2);
    }

    public static function weightedAverage(string|int $onHand, string|int $average, string|int $signedQuantity, string|int $unitCost): string
    {
        $next = bcadd(self::quantity($onHand), self::quantity($signedQuantity), 3);

        if (bccomp($next, '0.000', 3) === 0) {
            return '0.0000';
        }

        $value = bcadd(
            bcmul(self::quantity($onHand), self::cost($average), 8),
            bcmul(self::quantity($signedQuantity), self::cost($unitCost), 8),
            8,
        );

        return self::round(bcdiv($value, $next, 8), 4);
    }

    public static function compareQty(string|int $left, string|int $right): int
    {
        return bccomp(self::quantity($left), self::quantity($right), 3);
    }

    private static function scale(string|int $amount, int $scale): string
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Quantities and costs must not be floats.');
        }

        $amount = trim(is_int($amount) ? (string) $amount : $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Quantities and costs must be decimal strings.');
        }

        return self::round($amount, $scale);
    }

    private static function round(string $amount, int $scale): string
    {
        if (str_starts_with($amount, '-')) {
            return '-'.self::round(substr($amount, 1), $scale);
        }

        $increment = '0.'.str_repeat('0', $scale).'5';
        $bumped = bcadd($amount, $increment, $scale + 1);

        return bcadd($bumped, '0', $scale);
    }
}
