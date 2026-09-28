<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\DocumentStatus;
use App\Models\JournalEntryLine;
use App\Support\ChartAccountCode;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Profit and loss, the balance sheet, and the trial balance are totals of
 * journal lines. Income and expense stay open; current earnings are added
 * on the balance sheet and are not folded into retained earnings.
 */
final class FinancialStatementService
{
    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $rows = $this->movements($from, $to);
        $revenue = [];
        $cogs = [];
        $expenses = [];
        $totalRevenue = '0.00';
        $totalCogs = '0.00';
        $totalExpenses = '0.00';

        foreach ($rows as $row) {
            if ($row['type'] === AccountType::Income) {
                $amount = $this->signed($row, true);
                if (Money::of($amount)->isZero()) {
                    continue;
                }
                $revenue[] = $this->line($row, $amount);
                $totalRevenue = Money::of($totalRevenue)->add($amount)->amount();
            }

            if ($row['type'] !== AccountType::Expense) {
                continue;
            }

            $amount = $this->signed($row, false);

            if (Money::of($amount)->isZero()) {
                continue;
            }

            if ($row['code'] === ChartAccountCode::Cogs) {
                $cogs[] = $this->line($row, $amount);
                $totalCogs = Money::of($totalCogs)->add($amount)->amount();
            } else {
                $expenses[] = $this->line($row, $amount);
                $totalExpenses = Money::of($totalExpenses)->add($amount)->amount();
            }
        }

        $gross = Money::of($totalRevenue)->sub($totalCogs)->amount();
        $net = Money::of($gross)->sub($totalExpenses)->amount();

        return [
            'from' => $from,
            'to' => $to,
            'from_formatted' => Format::date($from),
            'to_formatted' => Format::date($to),
            'revenue' => $revenue,
            'total_revenue' => $this->money($totalRevenue),
            'cogs' => $cogs,
            'total_cogs' => $this->money($totalCogs),
            'gross_profit' => $this->money($gross),
            'expenses' => $expenses,
            'total_expenses' => $this->money($totalExpenses),
            'net_profit' => $this->money($net),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(string $asOf): array
    {
        $rows = $this->movements(null, $asOf);
        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = '0.00';
        $totalLiabilities = '0.00';
        $totalEquityAccounts = '0.00';
        $income = '0.00';
        $expense = '0.00';

        foreach ($rows as $row) {
            if ($row['type'] === AccountType::Asset) {
                $amount = $this->signed($row, false);
                if (! Money::of($amount)->isZero()) {
                    $assets[] = $this->line($row, $amount);
                    $totalAssets = Money::of($totalAssets)->add($amount)->amount();
                }
            } elseif ($row['type'] === AccountType::Liability) {
                $amount = $this->signed($row, true);
                if (! Money::of($amount)->isZero()) {
                    $liabilities[] = $this->line($row, $amount);
                    $totalLiabilities = Money::of($totalLiabilities)->add($amount)->amount();
                }
            } elseif ($row['type'] === AccountType::Equity) {
                $amount = $this->signed($row, true);
                if (! Money::of($amount)->isZero()) {
                    $equity[] = $this->line($row, $amount);
                    $totalEquityAccounts = Money::of($totalEquityAccounts)->add($amount)->amount();
                }
            } elseif ($row['type'] === AccountType::Income) {
                $income = Money::of($income)->add($this->signed($row, true))->amount();
            } elseif ($row['type'] === AccountType::Expense) {
                $expense = Money::of($expense)->add($this->signed($row, false))->amount();
            }
        }

        $earnings = Money::of($income)->sub($expense)->amount();
        $totalEquity = Money::of($totalEquityAccounts)->add($earnings)->amount();
        $right = Money::of($totalLiabilities)->add($totalEquity)->amount();

        return [
            'as_of' => $asOf,
            'as_of_formatted' => Format::date($asOf),
            'assets' => ['lines' => $assets, 'total' => $this->money($totalAssets)],
            'liabilities' => ['lines' => $liabilities, 'total' => $this->money($totalLiabilities)],
            'equity' => [
                'lines' => $equity,
                'current_earnings' => $this->money($earnings),
                'total' => $this->money($totalEquity),
            ],
            'liabilities_and_equity' => $this->money($right),
            'balanced' => Money::of($totalAssets)->compare($right) === 0,
            'difference' => $this->money(Money::of($totalAssets)->sub($right)->amount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trialBalance(string $asOf): array
    {
        $rows = [];
        $debitTotal = '0.00';
        $creditTotal = '0.00';

        foreach ($this->movements(null, $asOf) as $row) {
            $debitNet = Money::of($row['debit'])->sub($row['credit']);

            if ($debitNet->isZero()) {
                continue;
            }

            if ($debitNet->compare('0') === 1) {
                $debit = $debitNet->amount();
                $credit = '0.00';
            } else {
                $debit = '0.00';
                $credit = Money::of($row['credit'])->sub($row['debit'])->amount();
            }

            $rows[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'debit' => $this->money($debit),
                'credit' => $this->money($credit),
            ];
            $debitTotal = Money::of($debitTotal)->add($debit)->amount();
            $creditTotal = Money::of($creditTotal)->add($credit)->amount();
        }

        return [
            'as_of' => $asOf,
            'as_of_formatted' => Format::date($asOf),
            'rows' => $rows,
            'debit_total' => $this->money($debitTotal),
            'credit_total' => $this->money($creditTotal),
            'balanced' => Money::of($debitTotal)->compare($creditTotal) === 0,
        ];
    }

    /**
     * @return list<array{code: string, name: string, type: AccountType, debit: string, credit: string}>
     */
    private function movements(?string $from, ?string $through): array
    {
        $lines = JournalEntryLine::query()
            ->with('account')
            ->whereHas('entry', function (Builder $query) use ($from, $through): void {
                $query->whereIn('status', [DocumentStatus::Completed->value, DocumentStatus::Reversed->value]);

                if ($from !== null) {
                    $query->whereDate('entry_date', '>=', $from);
                }

                if ($through !== null) {
                    $query->whereDate('entry_date', '<=', $through);
                }
            })
            ->get();

        $accounts = [];

        foreach ($lines as $line) {
            $account = $line->account;
            $id = (int) $account->id;

            if (! isset($accounts[$id])) {
                $accounts[$id] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ];
            }

            $accounts[$id]['debit'] = Money::of($accounts[$id]['debit'])->add((string) $line->debit)->amount();
            $accounts[$id]['credit'] = Money::of($accounts[$id]['credit'])->add((string) $line->credit)->amount();
        }

        $rows = array_values($accounts);
        usort($rows, fn (array $left, array $right): int => strcmp($left['code'], $right['code']));

        return $rows;
    }

    /**
     * @param  array{debit: string, credit: string}  $row
     */
    private function signed(array $row, bool $creditMinusDebit): string
    {
        return $creditMinusDebit
            ? Money::of($row['credit'])->sub($row['debit'])->amount()
            : Money::of($row['debit'])->sub($row['credit'])->amount();
    }

    /**
     * @param  array{code: string, name: string}  $row
     * @return array{code: string, name: string, amount: string, formatted: string}
     */
    private function line(array $row, string $amount): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'amount' => $amount,
            'formatted' => Money::of($amount)->formatted(),
        ];
    }

    /**
     * @return array{amount: string, formatted: string}
     */
    private function money(string $amount): array
    {
        return [
            'amount' => $amount,
            'formatted' => Money::of($amount)->formatted(),
        ];
    }
}
