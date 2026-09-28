<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancialAccountType;
use App\Enums\PartnerStatus;
use App\Enums\PermissionName;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Approvals\ApprovalDirectory;
use App\Services\Finance\PartnerStatementService;
use App\Services\Inventory\StockQuery;
use App\Services\Reports\LedgerSlice;
use App\Services\Reports\StockValuation;
use App\Support\ChartAccountCode;
use App\Support\Format;
use App\Support\Money;
use Carbon\CarbonImmutable;

final class DashboardService
{
    public function __construct(
        private readonly PartnerDirectory $partners,
        private readonly ApprovalDirectory $approvals,
        private readonly FinancialStatementService $statements,
        private readonly LedgerSlice $ledger,
        private readonly StockValuation $stock,
        private readonly StockQuery $stockQuery,
        private readonly PartnerStatementService $partnerStatements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $actor): array
    {
        $visible = $this->partners->visibleTo($actor);
        $canSeeUsers = $actor->can(PermissionName::UserManage->value);
        $canSeeCash = $actor->can(PermissionName::AccountingView->value);
        $canSeeApprovals = $actor->can(PermissionName::ApprovalView->value)
            || $actor->can(PermissionName::PartnerInvestmentApprove->value)
            || $actor->can(PermissionName::PartnerWithdrawalApprove->value)
            || $actor->can(PermissionName::PartnerTransferApprove->value);
        $canSeeSales = $actor->can(PermissionName::SaleView->value);
        $canSeeStock = $actor->can(PermissionName::StockView->value);
        $canSeeExpenses = $actor->can(PermissionName::ExpenseView->value);
        $canSeeProfit = $actor->can(PermissionName::ProfitLossView->value);
        $canSeeInvestment = $actor->can(PermissionName::PartnerInvestmentView->value);
        $canSeeWithdrawal = $actor->can(PermissionName::PartnerWithdrawalView->value);
        $canSeePromotion = $actor->can(PermissionName::PromotionView->value);
        $canSeePayables = $actor->can(PermissionName::PurchaseView->value) || $canSeeCash;
        $canSeeReceivables = $canSeeSales || $canSeeCash;
        $today = now()->toDateString();
        $profit = ($canSeeProfit || $canSeeExpenses) ? $this->statements->profitAndLoss('2000-01-01', $today) : null;

        $cards = array_values(array_filter([
            $this->card('sales', 'Total sales', $canSeeSales, $canSeeSales ? $this->ledger->net(ChartAccountCode::ProductSales, null, null, true) : '0.00'),
            $this->card('investment', 'Investment', $canSeeInvestment, $canSeeInvestment ? $this->investment($actor) : '0.00'),
            $this->card('inventory_value', 'Inventory value', $canSeeStock, $canSeeStock ? $this->stock->current() : '0.00'),
            $this->card('expenses', 'Expenses', $canSeeExpenses, $profit['total_expenses']['amount'] ?? '0.00'),
            $this->card('gross_profit', 'Gross profit', $canSeeProfit, $profit['gross_profit']['amount'] ?? '0.00'),
            $this->card('net_profit', 'Net profit', $canSeeProfit, $profit['net_profit']['amount'] ?? '0.00'),
            $this->card('cash', 'Cash', $canSeeCash, $canSeeCash ? $this->ledger->typedBalanceAsOf(FinancialAccountType::Cash, $today) : '0.00'),
            $this->card('bank', 'Bank', $canSeeCash, $canSeeCash ? $this->ledger->typedBalanceAsOf(FinancialAccountType::Bank, $today) : '0.00'),
            $this->card('receivables', 'Accounts receivable', $canSeeReceivables, $canSeeReceivables ? $this->ledger->balanceAsOf(ChartAccountCode::AccountsReceivable, $today, true) : '0.00'),
            $this->card('payables', 'Accounts payable', $canSeePayables, $canSeePayables ? $this->ledger->balanceAsOf(ChartAccountCode::AccountsPayable, $today, false) : '0.00'),
            $this->card('pending_approvals', 'Pending approvals', $canSeeApprovals, (string) ($canSeeApprovals ? $this->approvals->countActionable($actor) : 0), false),
            $this->card('low_stock', 'Low stock', $canSeeStock, (string) ($canSeeStock ? count($this->stockQuery->lowStock()) : 0), false),
        ], fn (array $card): bool => $card['show']));

        return [
            'partners_total' => (clone $visible)->count(),
            'partners_active' => (clone $visible)->where('status', PartnerStatus::Active)->count(),
            'partners_inactive' => (clone $visible)->where('status', PartnerStatus::Inactive)->count(),
            'partners_suspended' => (clone $visible)->where('status', PartnerStatus::Suspended)->count(),
            'show_user_counts' => $canSeeUsers,
            'users_total' => $canSeeUsers ? User::query()->count() : null,
            'users_active' => $canSeeUsers ? User::query()->where('is_active', true)->count() : null,
            'recent_partners' => (clone $visible)
                ->latest('joining_date')
                ->limit(5)
                ->get(['id', 'partner_code', 'name', 'status', 'joining_date'])
                ->map(fn (Partner $partner): array => [
                    'id' => $partner->id,
                    'partner_code' => $partner->partner_code,
                    'name' => $partner->name,
                    'status' => [
                        'value' => $partner->status->value,
                        'label' => $partner->status->label(),
                        'tone' => $partner->status->tone(),
                    ],
                    'joining_date' => Format::date($partner->joining_date),
                ])
                ->all(),
            'show_cash' => $canSeeCash,
            'cash_and_bank' => $canSeeCash ? $this->cashAndBank() : null,
            'show_pending_approvals' => $canSeeApprovals,
            'pending_approvals' => $canSeeApprovals ? $this->approvals->countActionable($actor) : null,
            'show_sales' => $canSeeSales,
            'sales' => $canSeeSales ? Money::of($this->ledger->net(ChartAccountCode::ProductSales, null, null, true))->formatted() : null,
            'show_inventory' => $canSeeStock,
            'inventory_value' => $canSeeStock ? Money::of($this->stock->current())->formatted() : null,
            'cards' => $cards,
            'charts' => $this->charts($canSeeSales, $canSeeProfit, $canSeeInvestment && ! $this->partners->restrictsToOwnRecord($actor), $canSeeWithdrawal && ! $this->partners->restrictsToOwnRecord($actor), $canSeeExpenses, $canSeePromotion, $canSeeStock),
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, show: bool}
     */
    private function card(string $key, string $label, bool $show, string $amount, bool $money = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $money ? Money::of($amount)->formatted() : $amount,
            'show' => $show,
        ];
    }

    private function investment(User $actor): string
    {
        if ($this->partners->restrictsToOwnRecord($actor) && $actor->partner instanceof Partner) {
            return $this->partnerStatements->position($actor->partner)['investment']['amount'];
        }

        return $this->ledger->sourceNet(ChartAccountCode::PartnerCapital, [PartnerInvestment::class], null, null, true);
    }

    private function cashAndBank(): string
    {
        $total = '0.00';

        foreach (FinancialAccount::query()->where('is_active', true)->pluck('current_balance') as $balance) {
            $total = Money::of($total)->add((string) $balance)->amount();
        }

        return Money::of($total)->formatted();
    }

    /**
     * @return list<array{key: string, label: string, points: list<array{label: string, amount: string, formatted: string, width: string}>}>
     */
    private function charts(
        bool $sales,
        bool $profit,
        bool $investment,
        bool $withdrawal,
        bool $expenses,
        bool $promotion,
        bool $stock,
    ): array {
        $months = [];
        $cursor = CarbonImmutable::now()->startOfMonth();

        for ($i = 5; $i >= 0; $i--) {
            $month = $cursor->subMonths($i);
            $from = $month->toDateString();
            $to = $month->endOfMonth()->toDateString();
            $statement = ($profit || $expenses || $sales) ? $this->statements->profitAndLoss($from, $to) : null;
            $months[] = [
                'label' => $month->format('M Y'),
                'sales' => $statement['total_revenue']['amount'] ?? $this->ledger->net(ChartAccountCode::ProductSales, $from, $to, true),
                'profit' => $statement['net_profit']['amount'] ?? '0.00',
                'expenses' => $statement['total_expenses']['amount'] ?? '0.00',
                'promotion' => $this->ledger->net(ChartAccountCode::PromotionExpense, $from, $to, false),
                'investment' => $this->ledger->sourceNet(ChartAccountCode::PartnerCapital, [PartnerInvestment::class], $from, $to, true),
                'withdrawal' => $this->ledger->sourceNet(ChartAccountCode::PartnerWithdrawals, [PartnerWithdrawal::class], $from, $to, false),
                'stock' => $stock ? $this->stock->asOf($to) : '0.00',
            ];
        }

        $series = array_values(array_filter([
            $sales ? $this->series('sales', 'Monthly sales', $months, 'sales') : null,
            $profit ? $this->series('profit', 'Monthly profit', $months, 'profit') : null,
            $investment ? $this->series('investment', 'Monthly investment', $months, 'investment') : null,
            $withdrawal ? $this->series('withdrawal', 'Monthly withdrawal', $months, 'withdrawal') : null,
            $expenses ? $this->series('expenses', 'Monthly expenses', $months, 'expenses') : null,
            $promotion ? $this->series('promotion', 'Monthly promotion', $months, 'promotion') : null,
            $stock ? $this->series('stock', 'Stock value', $months, 'stock') : null,
        ]));

        return $series;
    }

    /**
     * @param  list<array<string, string>>  $months
     * @return array{key: string, label: string, points: list<array{label: string, amount: string, formatted: string, width: string}>}
     */
    private function series(string $key, string $label, array $months, string $field): array
    {
        $max = '0.00';
        foreach ($months as $month) {
            $absolute = ltrim($month[$field], '-');
            if (Money::of($absolute)->compare($max) === 1) {
                $max = $absolute;
            }
        }

        $points = [];
        foreach ($months as $month) {
            $amount = $month[$field];
            $absolute = ltrim($amount, '-');
            $width = Money::of($max)->isZero() ? '0' : bcmul(bcdiv($absolute, $max, 4), '100', 1);
            $points[] = [
                'label' => $month['label'],
                'amount' => $amount,
                'formatted' => Money::of($amount)->formatted(),
                'width' => $width.'%',
            ];
        }

        return ['key' => $key, 'label' => $label, 'points' => $points];
    }
}
