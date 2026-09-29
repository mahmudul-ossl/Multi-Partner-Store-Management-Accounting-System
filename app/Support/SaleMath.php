<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sale totals are subtotal - discount + delivery. Line nets share that
 * total so a full return reverses the same revenue, including delivery.
 */
final class SaleMath
{
    public static function total(string|int $subtotal, string|int $discount, string|int $delivery): string
    {
        return Money::of($subtotal)->sub($discount)->add($delivery)->amount();
    }

    /**
     * @param  list<string|int>  $parts
     * @return list<string>
     */
    public static function allocate(array $parts, string|int $total): array
    {
        $total = Money::of($total)->amount();
        $subtotal = '0.00';

        foreach ($parts as $part) {
            $subtotal = Money::of($subtotal)->add($part)->amount();
        }

        $shares = [];
        $running = '0.00';
        $last = count($parts) - 1;

        foreach ($parts as $index => $part) {
            if ($index === $last) {
                $shares[] = Money::of($total)->sub($running)->amount();
                break;
            }

            $raw = bcdiv(bcmul(Money::of($part)->amount(), $total, 6), $subtotal, 6);
            $share = Costing::money($raw);
            $shares[] = $share;
            $running = Money::of($running)->add($share)->amount();
        }

        return $shares;
    }

    public static function portion(string|int $amount, string|int $partQuantity, string|int $fullQuantity): string
    {
        $full = Costing::quantity($fullQuantity);
        $part = Costing::quantity($partQuantity);

        if (Costing::compareQty($part, $full) === 0) {
            return Money::of($amount)->amount();
        }

        $raw = bcdiv(bcmul(Money::of($amount)->amount(), $part, 6), $full, 6);

        return Costing::money($raw);
    }
}
