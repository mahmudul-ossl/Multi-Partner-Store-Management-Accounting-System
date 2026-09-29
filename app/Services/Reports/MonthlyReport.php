<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\FinancialAccountType;
use App\Models\PartnerInvestment;
use App\Models\PartnerWithdrawal;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\Accounting\FinancialStatementService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * One month (or a custom range) assembled from the ledger and the stock ledger.
 */
final class MonthlyReport
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly LedgerSlice $ledger,
        private readonly StockValuation $stock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $from, string $to): array
    {
        $profit = $this->statements->profitAndLoss($from, $to);
        $openingDay = Carbon::parse($from)->subDay()->toDateString();

        $figures = [
            $this->figure('sales', 'Sales', $profit['total_revenue']['amount']),
            $this->figure('purchases', 'Purchases', $this->ledger->sourceNet(
                ChartAccountCode::Inventory,
                [Purchase::class, PurchaseReturn::class],
                $from,
                $to,
                false,
            )),
            $this->figure('cogs', 'COGS', $profit['total_cogs']['amount']),
            $this->figure('gross_profit', 'Gross profit', $profit['gross_profit']['amount']),
            $this->figure('expenses', 'Expenses', $profit['total_expenses']['amount']),
            $this->figure('net_profit', 'Net profit', $profit['net_profit']['amount']),
            $this->figure('opening_cash', 'Opening cash', $this->ledger->typedBalanceAsOf(FinancialAccountType::Cash, $openingDay)),
            $this->figure('closing_cash', 'Closing cash', $this->ledger->typedBalanceAsOf(FinancialAccountType::Cash, $to)),
            $this->figure('opening_stock', 'Opening stock', $this->stock->asOf($openingDay)),
            $this->figure('closing_stock', 'Closing stock', $this->stock->asOf($to)),
            $this->figure('investment', 'Investment', $this->ledger->sourceNet(
                ChartAccountCode::PartnerCapital,
                [PartnerInvestment::class],
                $from,
                $to,
                true,
            )),
            $this->figure('withdrawal', 'Withdrawal', $this->ledger->sourceNet(
                ChartAccountCode::PartnerWithdrawals,
                [PartnerWithdrawal::class],
                $from,
                $to,
                false,
            )),
            $this->figure('promotion', 'Promotion', $this->ledger->net(ChartAccountCode::PromotionExpense, $from, $to, false)),
            $this->figure('receivables', 'Accounts receivable', $this->ledger->balanceAsOf(ChartAccountCode::AccountsReceivable, $to, true)),
            $this->figure('payables', 'Accounts payable', $this->ledger->balanceAsOf(ChartAccountCode::AccountsPayable, $to, false)),
        ];

        return [
            'from' => $from,
            'to' => $to,
            'figures' => $figures,
            'expenses' => $profit['expenses'],
            'gross_profit' => $profit['gross_profit'],
            'net_profit' => $profit['net_profit'],
            'sales' => $profit['total_revenue'],
        ];
    }

    /**
     * @return array{key: string, label: string, amount: string, formatted: string}
     */
    private function figure(string $key, string $label, string $amount): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'amount' => $amount,
            'formatted' => Money::of($amount)->formatted(),
        ];
    }
}
