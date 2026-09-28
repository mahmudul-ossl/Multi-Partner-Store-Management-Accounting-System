<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal money. Floats are rejected so later ledger code cannot silently
 * lose precision. Storage columns must be decimal(18, 2).
 */
final class Money
{
    private function __construct(private readonly string $amount) {}

    public static function of(mixed $amount): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Money must not be constructed from a float.');
        }

        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money must be a decimal string or an integer.');
        }

        if (is_int($amount)) {
            $amount = (string) $amount;
        }

        $amount = trim($amount);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Money must be a decimal string with up to 2 fraction digits.');
        }

        return new self(bcadd($amount, '0', 2));
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function formatted(): string
    {
        $symbol = (string) config('mpstore.currency.symbol', '৳');
        $negative = str_starts_with($this->amount, '-');
        $absolute = ltrim($this->amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '00');
        $fraction = str_pad($fraction, 2, '0');

        return ($negative ? '-' : '').$symbol.self::groupThousands($whole).'.'.$fraction;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0.00', 2) === 0;
    }

    private static function groupThousands(string $digits): string
    {
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            $digits = '0';
        }

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits);

        return $grouped ?? $digits;
    }
}
