<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PartnerStatus;
use App\Models\Expense;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\ProfitAllocation;
use App\Models\PromotionPartnerExpense;
use App\Support\ChartAccountCode;
use App\Support\Format;
use App\Support\Money;

/**
 * Partner profile figures use the same ledger as the partner statement.
 * The investment share uses the active partners' stored investment
 * percentages, which is the investment-method profit allocation basis.
 */
final class PartnerProfileService
{
    public const INVESTMENT_BASIS = 'Investment method: stored investment percentages of active partners, divided by their total. This is the same basis an investment-method profit allocation uses. These shares add up to 100%.';

    public const CAPITAL_BASIS = 'Share of total partner capital: this partner\'s current capital divided by every partner\'s current capital. Current capital is the net of Partner Capital (3000) and Partner Withdrawals (3200), the same accounts as the partner statement. Shares are rounded like a profit allocation so they add up to 100%.';

    public function __construct(private readonly PartnerStatementService $statements) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Partner $partner): array
    {
        $position = $this->statements->position($partner);
        $investment = $this->investmentShares();
        $capital = $this->capitalShares();
        $stored = bcadd((string) $partner->investment_percentage, '0', 4);

        return [
            'gross_investment' => $position['investment'],
            'withdrawals' => $position['withdrawal'],
            'promotion_contribution' => $position['promotion_contribution'],
            'partner_expenses' => $position['expenses'],
            'allocated_profit' => $position['profit_share'],
            'net_capital' => $position['current_capital'],
            'stored_investment_percentage' => $stored,
            'stored_investment_percentage_display' => Format::percent($stored),
            'investment_share' => $investment['shares'][$partner->id] ?? '0.0000',
            'investment_share_display' => $this->percent($investment['shares'][$partner->id] ?? '0.0000'),
            'investment_basis_total' => $investment['basis'],
            'investment_basis_label' => self::INVESTMENT_BASIS,
            'in_investment_basis' => $investment['included'][$partner->id] ?? false,
            'capital_share' => $capital['shares'][$partner->id] ?? '0.0000',
            'capital_share_display' => $this->percent($capital['shares'][$partner->id] ?? '0.0000'),
            'capital_total' => $this->money($capital['total']),
            'capital_basis_label' => self::CAPITAL_BASIS,
        ];
    }

    /**
     * @return array{basis: string, shares: array<int, string>, included: array<int, bool>}
     */
    public function investmentShares(): array
    {
        $partners = Partner::query()->orderBy('id')->get();
        $weights = [];

        foreach ($partners as $partner) {
            $percentage = bcadd((string) $partner->investment_percentage, '0', 4);
            if ($partner->status === PartnerStatus::Active && bccomp($percentage, '0.0000', 4) === 1) {
                $weights[] = ['id' => (int) $partner->id, 'percentage' => $percentage];
            }
        }

        $basis = '0.0000';
        foreach ($weights as $weight) {
            $basis = bcadd($basis, $weight['percentage'], 4);
        }

        $shares = [];
        $included = [];
        foreach ($partners as $partner) {
            $shares[(int) $partner->id] = '0.0000';
            $included[(int) $partner->id] = false;
        }

        $running = '0.0000';
        $last = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            $included[$weight['id']] = true;
            if ($index === $last) {
                $shares[$weight['id']] = bcsub('100.0000', $running, 4);

                continue;
            }

            $raw = bcdiv(bcmul($weight['percentage'], '100', 8), $basis, 8);
            $share = $this->round4($raw);
            $running = bcadd($running, $share, 4);
            $shares[$weight['id']] = $share;
        }

        return [
            'basis' => $basis,
            'shares' => $shares,
            'included' => $included,
        ];
    }

    /**
     * @return array{total: string, shares: array<int, string>}
     */
    public function capitalShares(): array
    {
        $partners = Partner::query()->orderBy('id')->get();
        $amounts = [];
        $total = '0.00';

        foreach ($partners as $partner) {
            $amount = $this->statements->capitalBalance($partner);
            $amounts[(int) $partner->id] = $amount;
            $total = Money::of($total)->add($amount)->amount();
        }

        $shares = [];
        $running = '0.0000';
        $ids = array_keys($amounts);
        $last = count($ids) - 1;
        $zeroTotal = bccomp($total, '0.00', 2) === 0;

        foreach ($ids as $index => $id) {
            if ($zeroTotal) {
                $shares[$id] = '0.0000';

                continue;
            }

            if ($index === $last) {
                $shares[$id] = bcsub('100.0000', $running, 4);

                continue;
            }

            $raw = bcdiv(bcmul($amounts[$id], '100', 8), $total, 8);
            $share = $this->round4($raw);
            $running = bcadd($running, $share, 4);
            $shares[$id] = $share;
        }

        return [
            'total' => $total,
            'shares' => $shares,
        ];
    }

    /**
     * @return array{opening: array{amount: string, formatted: string}, debit_total: array{amount: string, formatted: string}, credit_total: array{amount: string, formatted: string}, closing: array{amount: string, formatted: string}, lines: list<array<string, mixed>>}
     */
    public function history(Partner $partner, ?string $from, ?string $to): array
    {
        $rows = JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->where('journal_entry_lines.partner_id', $partner->id)
            ->whereIn('chart_of_accounts.code', ChartAccountCode::PARTNER_STATEMENT)
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.id')
            ->with(['entry', 'account'])
            ->get();

        $balance = '0.00';
        $opening = '0.00';
        $debitTotal = '0.00';
        $creditTotal = '0.00';
        $lines = [];

        foreach ($rows as $line) {
            $date = $line->entry->entry_date?->toDateString() ?? '';
            $balance = Money::of($balance)->add((string) $line->credit)->sub((string) $line->debit)->amount();

            if ($from !== null && $date < $from) {
                $opening = $balance;

                continue;
            }

            if ($to !== null && $date > $to) {
                continue;
            }

            $debitTotal = Money::of($debitTotal)->add((string) $line->debit)->amount();
            $creditTotal = Money::of($creditTotal)->add((string) $line->credit)->amount();
            $lines[] = [
                'date' => Format::date($line->entry->entry_date),
                'reference' => (string) $line->entry->reference,
                'type' => $this->typeLabel($line->entry->source_type, $line->entry->reversal_of),
                'description' => $line->description ?: (string) $line->entry->description,
                'account' => $line->account->code.' · '.$line->account->name,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'debit_formatted' => Money::of((string) $line->debit)->formatted(),
                'credit_formatted' => Money::of((string) $line->credit)->formatted(),
                'running_balance' => $balance,
                'running_balance_formatted' => Money::of($balance)->formatted(),
            ];
        }

        $closing = $lines === [] ? $opening : (string) $lines[array_key_last($lines)]['running_balance'];

        return [
            'opening' => $this->money($opening),
            'debit_total' => $this->money($debitTotal),
            'credit_total' => $this->money($creditTotal),
            'closing' => $this->money($closing),
            'lines' => $lines,
        ];
    }

    private function typeLabel(?string $sourceType, mixed $reversalOf): string
    {
        $name = match ($sourceType) {
            PartnerInvestment::class => 'Investment',
            PartnerWithdrawal::class => 'Withdrawal',
            PartnerTransfer::class => 'Transfer',
            ProfitAllocation::class => 'Profit allocation',
            PromotionPartnerExpense::class => 'Promotion contribution',
            Expense::class => 'Expense',
            default => 'Journal',
        };

        return $reversalOf === null ? $name : 'Reversal · '.$name;
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

    private function percent(string $value): string
    {
        return $value.'%';
    }

    private function round4(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');

        return ($negative ? '-' : '').bcadd($absolute, '0.00005', 4);
    }
}
