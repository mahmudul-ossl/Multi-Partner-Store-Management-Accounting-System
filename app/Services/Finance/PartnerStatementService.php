<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\ProfitAllocation;
use App\Models\ProfitAllocationLine;
use App\Models\PromotionPartnerExpense;
use App\Support\ChartAccountCode;
use App\Support\Format;
use App\Support\LedgerSource;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Partner position and statement figures come from journal lines.
 * Investment minus withdrawal is never used as a balance.
 */
final class PartnerStatementService
{
    /**
     * @return array<string, mixed>
     */
    public function position(Partner $partner): array
    {
        $investment = $this->netMovement($partner, ChartAccountCode::PartnerCapital, PartnerInvestment::class);
        $withdrawal = $this->netMovement($partner, ChartAccountCode::PartnerWithdrawals, PartnerWithdrawal::class, false);
        $promotion = $this->netMovement($partner, ChartAccountCode::PartnerCapital, LedgerSource::PromotionContribution);
        $expenses = $this->netMovement($partner, ChartAccountCode::PartnerCapital, LedgerSource::PartnerExpense);
        $profitShare = $this->netMovement($partner, ChartAccountCode::PartnerCapital, LedgerSource::ProfitShare);
        $capital = $this->capitalBalance($partner);
        $counts = $this->requestCounts($partner);

        return [
            'investment' => $this->money($investment),
            'withdrawal' => $this->money($withdrawal),
            'promotion_contribution' => $this->money($promotion),
            'expenses' => $this->money($expenses),
            'profit_share' => $this->money($profitShare),
            'current_capital' => $this->money($capital),
            'requests' => $counts,
            'recent' => array_slice(array_reverse($this->lines($partner)), 0, 8),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function statement(Partner $partner): array
    {
        return $this->lines($partner);
    }

    public function capitalBalance(Partner $partner): string
    {
        $lines = JournalEntryLine::query()
            ->where('partner_id', $partner->id)
            ->whereHas('account', fn (Builder $query) => $query->whereIn('code', ChartAccountCode::PARTNER_STATEMENT))
            ->get(['debit', 'credit']);

        $balance = '0.00';

        foreach ($lines as $line) {
            $balance = Money::of($balance)->add((string) $line->credit)->sub((string) $line->debit)->amount();
        }

        return $balance;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(Partner $partner): array
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
            ->with('entry')
            ->get();

        $balance = '0.00';
        $statement = [];

        foreach ($rows as $line) {
            $balance = Money::of($balance)->add((string) $line->credit)->sub((string) $line->debit)->amount();
            $statement[] = [
                'date' => Format::date($line->entry->entry_date),
                'description' => $line->description ?: $line->entry->description,
                'reference' => $line->entry->reference,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'debit_formatted' => Money::of((string) $line->debit)->formatted(),
                'credit_formatted' => Money::of((string) $line->credit)->formatted(),
                'running_balance' => $balance,
                'running_balance_formatted' => Money::of($balance)->formatted(),
            ];
        }

        return $statement;
    }

    private function netMovement(Partner $partner, string $accountCode, string $sourceType, bool $creditMinusDebit = true): string
    {
        $lines = JournalEntryLine::query()
            ->where('partner_id', $partner->id)
            ->whereHas('account', fn (Builder $query) => $query->where('code', $accountCode))
            ->whereHas('entry', function (Builder $query) use ($sourceType): void {
                $query->where('source_type', $sourceType)
                    ->orWhereIn('reversal_of', function ($inner) use ($sourceType): void {
                        $inner->select('id')->from('journal_entries')->where('source_type', $sourceType);
                    });
            })
            ->get(['debit', 'credit']);

        $net = '0.00';

        foreach ($lines as $line) {
            $movement = $creditMinusDebit
                ? Money::of((string) $line->credit)->sub((string) $line->debit)
                : Money::of((string) $line->debit)->sub((string) $line->credit);
            $net = Money::of($net)->add($movement)->amount();
        }

        return $net;
    }

    /**
     * @return array{pending: int, approved: int, rejected: int}
     */
    private function requestCounts(Partner $partner): array
    {
        $investmentIds = PartnerInvestment::query()->where('partner_id', $partner->id)->pluck('id');
        $withdrawalIds = PartnerWithdrawal::query()->where('partner_id', $partner->id)->pluck('id');
        $transferIds = PartnerTransfer::query()
            ->where('from_partner_id', $partner->id)
            ->orWhere('to_partner_id', $partner->id)
            ->pluck('id');
        $contributionIds = PromotionPartnerExpense::query()->where('partner_id', $partner->id)->pluck('id');
        $expenseIds = Expense::query()->where('partner_id', $partner->id)->pluck('id');
        $allocationIds = ProfitAllocationLine::query()->where('partner_id', $partner->id)->pluck('profit_allocation_id');

        $requests = ApprovalRequest::query()
            ->where(function (Builder $query) use ($investmentIds, $withdrawalIds, $transferIds, $contributionIds, $expenseIds, $allocationIds): void {
                $query->where(fn (Builder $query) => $query->where('reference_type', PartnerInvestment::class)->whereIn('reference_id', $investmentIds))
                    ->orWhere(fn (Builder $query) => $query->where('reference_type', PartnerWithdrawal::class)->whereIn('reference_id', $withdrawalIds))
                    ->orWhere(fn (Builder $query) => $query->where('reference_type', PartnerTransfer::class)->whereIn('reference_id', $transferIds))
                    ->orWhere(fn (Builder $query) => $query->where('reference_type', PromotionPartnerExpense::class)->whereIn('reference_id', $contributionIds))
                    ->orWhere(fn (Builder $query) => $query->where('reference_type', Expense::class)->whereIn('reference_id', $expenseIds))
                    ->orWhere(fn (Builder $query) => $query->where('reference_type', ProfitAllocation::class)->whereIn('reference_id', $allocationIds));
            })
            ->get(['status']);

        return [
            'pending' => $requests->filter(fn ($request): bool => $request->status->isOpen())->count(),
            'approved' => $requests->where('status', ApprovalStatus::Approved)->count(),
            'rejected' => $requests->where('status', ApprovalStatus::Rejected)->count(),
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
