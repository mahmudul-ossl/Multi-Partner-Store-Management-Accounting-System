<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApprovalStateException;

/**
 * Splits an allocation so the partner amounts add up to the total.
 * Every share except the last is rounded half-up to paisa. The last
 * partner receives whatever is left.
 */
final class AllocationSplit
{
    /**
     * @param  list<array{partner_id: int, percentage: string}>  $weights
     * @return list<array{partner_id: int, percentage: string, amount: string}>
     */
    public static function apply(string|int $amount, array $weights): array
    {
        if ($weights === []) {
            throw new ApprovalStateException('The allocation needs at least one partner.');
        }

        $amount = Money::of($amount)->amount();
        $basis = '0.0000';

        foreach ($weights as $row) {
            $basis = bcadd($basis, $row['percentage'], 4);
        }

        if (bccomp($basis, '0.0000', 4) !== 1) {
            throw new ApprovalStateException('The allocation needs at least one partner share.');
        }

        $lines = [];
        $running = '0.00';
        $last = count($weights) - 1;

        foreach ($weights as $index => $row) {
            if ($index === $last) {
                $share = Money::of($amount)->sub($running)->amount();
            } else {
                $raw = bcdiv(bcmul($amount, $row['percentage'], 8), $basis, 8);
                $share = Costing::money($raw);
                $running = Money::of($running)->add($share)->amount();
            }

            if (Money::of($share)->compare('0.00') === -1) {
                throw new ApprovalStateException('The allocation could not be rounded without a negative share.');
            }

            $lines[] = [
                'partner_id' => $row['partner_id'],
                'percentage' => $row['percentage'],
                'amount' => $share,
            ];
        }

        return $lines;
    }
}
