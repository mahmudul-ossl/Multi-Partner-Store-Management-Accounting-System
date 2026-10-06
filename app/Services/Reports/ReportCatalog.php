<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * The report catalogue. Each report keeps the permission that already guards that area.
 */
final class ReportCatalog
{
    /**
     * @return list<array{key: string, label: string, permission: PermissionName}>
     */
    public static function all(): array
    {
        return [
            self::entry('sales', 'Sales', PermissionName::SaleView),
            self::entry('purchases', 'Purchases', PermissionName::PurchaseView),
            self::entry('product-sales', 'Product sales', PermissionName::SaleView),
            self::entry('stock', 'Stock', PermissionName::StockView),
            self::entry('stock-movements', 'Stock movements', PermissionName::StockView),
            self::entry('investments', 'Investments', PermissionName::PartnerInvestmentView),
            self::entry('withdrawals', 'Withdrawals', PermissionName::PartnerWithdrawalView),
            self::entry('partner-statement', 'Partner statement', PermissionName::PartnerStatementView),
            self::entry('partner-balances', 'Partner balances', PermissionName::PartnerStatementView),
            self::entry('promotions', 'Promotions', PermissionName::PromotionView),
            self::entry('expenses', 'Expenses', PermissionName::ExpenseView),
            self::entry('cash', 'Cash', PermissionName::AccountingView),
            self::entry('bank', 'Bank', PermissionName::AccountingView),
            self::entry('receivables', 'Accounts receivable', PermissionName::SaleView),
            self::entry('payables', 'Accounts payable', PermissionName::PurchaseView),
            self::entry('profit-loss', 'Profit and loss', PermissionName::ProfitLossView),
            self::entry('balance-sheet', 'Balance sheet', PermissionName::BalanceSheetView),
            self::entry('trial-balance', 'Trial balance', PermissionName::TrialBalanceView),
            self::entry('general-ledger', 'General ledger', PermissionName::AccountingView),
            self::entry('monthly', 'Monthly business report', PermissionName::ReportView),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function visible(User $user): array
    {
        $reports = [];

        foreach (self::all() as $report) {
            if ($user->can($report['permission']->value)) {
                $reports[] = ['key' => $report['key'], 'label' => $report['label']];
            }
        }

        return $reports;
    }

    /**
     * @return array{key: string, label: string, permission: PermissionName}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $report) {
            if ($report['key'] === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, permission: PermissionName}
     */
    private static function entry(string $key, string $label, PermissionName $permission): array
    {
        return ['key' => $key, 'label' => $label, 'permission' => $permission];
    }
}
