<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Exceptions\UnbalancedEntryException;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Invariant for the future journal: every entry's debits equal its credits.
 * Journal posting in later phases should refuse to persist until this passes.
 */
final class BalancedEntry
{
    /**
     * @param  list<array{debit: string|int, credit: string|int}>  $lines
     */
    public static function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A journal entry needs at least one line.');
        }

        $debits = '0.00';
        $credits = '0.00';

        foreach ($lines as $line) {
            $debit = Money::of($line['debit'])->amount();
            $credit = Money::of($line['credit'])->amount();

            if (bccomp($debit, '0.00', 2) === 1 && bccomp($credit, '0.00', 2) === 1) {
                throw new InvalidArgumentException('A journal line cannot carry both a debit and a credit.');
            }

            $debits = bcadd($debits, $debit, 2);
            $credits = bcadd($credits, $credit, 2);
        }

        if (bccomp($debits, $credits, 2) !== 0) {
            throw new UnbalancedEntryException("Debits {$debits} do not equal credits {$credits}.");
        }
    }
}
