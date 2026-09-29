<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DocumentStatus;
use App\Enums\FinancialAccountType;
use App\Models\JournalEntryLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Date-bounded ledger totals. Amounts stay decimal strings.
 */
final class LedgerSlice
{
    public function net(string $code, ?string $from, ?string $through, bool $creditMinusDebit): string
    {
        return $this->sum($this->lines($from, $through)->whereHas(
            'account',
            fn (Builder $query) => $query->where('code', $code),
        )->get(['debit', 'credit']), $creditMinusDebit);
    }

    public function balanceAsOf(string $code, string $asOf, bool $debitNormal): string
    {
        return $this->net($code, null, $asOf, ! $debitNormal);
    }

    public function typedBalanceAsOf(FinancialAccountType $type, string $asOf): string
    {
        $lines = $this->lines(null, $asOf)
            ->whereHas('financialAccount', fn (Builder $query) => $query->where('type', $type->value))
            ->get(['debit', 'credit']);

        return $this->sum($lines, false);
    }

    /**
     * @param  list<class-string>  $sources
     */
    public function sourceNet(string $code, array $sources, ?string $from, ?string $through, bool $creditMinusDebit): string
    {
        $lines = $this->lines($from, $through)
            ->whereHas('account', fn (Builder $query) => $query->where('code', $code))
            ->whereHas('entry', fn (Builder $query) => $query->whereIn('source_type', $sources))
            ->get(['debit', 'credit']);

        return $this->sum($lines, $creditMinusDebit);
    }

    private function lines(?string $from, ?string $through): Builder
    {
        return JournalEntryLine::query()->whereHas('entry', function (Builder $query) use ($from, $through): void {
            $query->whereIn('status', [DocumentStatus::Completed->value, DocumentStatus::Reversed->value]);

            if ($from !== null && $from !== '') {
                $query->whereDate('entry_date', '>=', $from);
            }

            if ($through !== null && $through !== '') {
                $query->whereDate('entry_date', '<=', $through);
            }
        });
    }

    /**
     * @param  iterable<int, mixed>  $lines
     */
    private function sum(iterable $lines, bool $creditMinusDebit): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            $movement = $creditMinusDebit
                ? Money::of((string) $line->credit)->sub((string) $line->debit)
                : Money::of((string) $line->debit)->sub((string) $line->credit);
            $total = Money::of($total)->add($movement)->amount();
        }

        return $total;
    }
}
