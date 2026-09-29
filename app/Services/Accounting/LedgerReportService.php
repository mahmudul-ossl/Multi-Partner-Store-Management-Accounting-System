<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\FinancialAccountType;
use App\Enums\NormalBalance;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntryLine;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class LedgerReportService
{
    public function __construct(private readonly FinancialAccountService $accounts) {}

    /**
     * @return array<string, mixed>
     */
    public function generalLedger(ChartOfAccount $account, ?string $from, ?string $to): array
    {
        $opening = '0.00';

        if ($from !== null && $from !== '') {
            $prior = $this->lines($account, null, $this->dayBefore($from));
            $opening = $this->signed($prior, $account->normal_balance);
        }

        $rows = $this->lines(
            $account,
            $from !== null && $from !== '' ? $from : null,
            $to !== null && $to !== '' ? $to : null,
        );

        $running = $opening;
        $presented = [];

        foreach ($rows as $line) {
            $movement = $this->lineMovement($line, $account->normal_balance);
            $running = Money::of($running)->add($movement)->amount();
            $presented[] = [
                'id' => $line->id,
                'date' => Format::date($line->entry?->entry_date),
                'reference' => $line->entry?->reference,
                'journal_id' => $line->journal_entry_id,
                'description' => $line->description ?: $line->entry?->description,
                'partner' => $line->partner?->name,
                'financial_account' => $line->financialAccount?->name,
                'debit' => $this->money((string) $line->debit),
                'credit' => $this->money((string) $line->credit),
                'running_balance' => $this->money($running),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->label(),
                'normal_balance' => $account->normal_balance->label(),
            ],
            'opening_balance' => $this->money($opening),
            'closing_balance' => $this->money($running),
            'lines' => $presented,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashReport(): array
    {
        return $this->financialReport(FinancialAccountType::Cash);
    }

    /**
     * @return array{banks: list<array<string, mixed>>, wallets: list<array<string, mixed>>}
     */
    public function bankReport(): array
    {
        return [
            'banks' => $this->financialReport(FinancialAccountType::Bank),
            'wallets' => $this->financialReport(FinancialAccountType::MobileWallet),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function financialReport(FinancialAccountType $type): array
    {
        return FinancialAccount::query()
            ->where('type', $type)
            ->orderBy('name')
            ->get()
            ->map(function (FinancialAccount $account): array {
                $ledger = $this->accounts->ledgerBalance($account);
                $cached = (string) $account->current_balance;

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'is_active' => $account->is_active,
                    'cached_balance' => $this->money($cached),
                    'ledger_balance' => $this->money($ledger),
                    'reconciled' => Money::of($cached)->compare($ledger) === 0,
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, JournalEntryLine>
     */
    private function lines(ChartOfAccount $account, ?string $from, ?string $to): Collection
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.chart_of_account_id', $account->id)
            ->when($from, fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $to))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->with(['entry', 'partner', 'financialAccount'])
            ->get();
    }

    /**
     * @param  Collection<int, JournalEntryLine>  $lines
     */
    private function signed(Collection $lines, NormalBalance $normal): string
    {
        $balance = '0.00';

        foreach ($lines as $line) {
            $balance = Money::of($balance)->add($this->lineMovement($line, $normal))->amount();
        }

        return $balance;
    }

    private function lineMovement(JournalEntryLine $line, NormalBalance $normal): string
    {
        if ($normal === NormalBalance::Credit) {
            return Money::of((string) $line->credit)->sub((string) $line->debit)->amount();
        }

        return Money::of((string) $line->debit)->sub((string) $line->credit)->amount();
    }

    private function dayBefore(string $date): string
    {
        return Carbon::parse($date)->subDay()->toDateString();
    }

    /**
     * @return array{amount: string, formatted: string}
     */
    private function money(string $amount): array
    {
        $money = Money::of($amount);

        return [
            'amount' => $money->amount(),
            'formatted' => $money->formatted(),
        ];
    }
}
