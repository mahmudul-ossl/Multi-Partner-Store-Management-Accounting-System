<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Documents that share the approval workflow. Later phases post their own
 * effects but must keep these type values.
 */
enum ApprovalRequestType: string
{
    case Investment = 'investment';
    case Withdrawal = 'withdrawal';
    case PartnerTransfer = 'partner_transfer';
    case PartnerExpense = 'partner_expense';
    case PromotionExpense = 'promotion_expense';
    case Purchase = 'purchase';
    case SalesIncome = 'sales_income';
    case StockAdjustment = 'stock_adjustment';
    case Refund = 'refund';
    case LargeDiscount = 'large_discount';
    case SalesCancellation = 'sales_cancellation';
    case ManualJournal = 'manual_journal';
    case AccountTransfer = 'account_transfer';
    case ProfitAllocation = 'profit_allocation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Investment => 'Partner investment',
            self::Withdrawal => 'Partner withdrawal',
            self::PartnerTransfer => 'Partner transfer',
            self::PartnerExpense => 'Partner expense',
            self::PromotionExpense => 'Promotion expense',
            self::Purchase => 'Purchase',
            self::SalesIncome => 'Sales income',
            self::StockAdjustment => 'Stock adjustment',
            self::Refund => 'Refund',
            self::LargeDiscount => 'Large discount',
            self::SalesCancellation => 'Sales cancellation',
            self::ManualJournal => 'Manual journal',
            self::AccountTransfer => 'Account transfer',
            self::ProfitAllocation => 'Profit allocation',
            self::Other => 'Other',
        };
    }

    public function approvePermission(): PermissionName
    {
        return match ($this) {
            self::Investment => PermissionName::PartnerInvestmentApprove,
            self::Withdrawal => PermissionName::PartnerWithdrawalApprove,
            self::PartnerTransfer => PermissionName::PartnerTransferApprove,
            self::PartnerExpense => PermissionName::ExpenseApprove,
            self::PromotionExpense => PermissionName::PromotionApprove,
            self::Purchase => PermissionName::PurchaseApprove,
            self::SalesIncome => PermissionName::SaleCancel,
            self::StockAdjustment => PermissionName::StockAdjustApprove,
            self::Refund, self::LargeDiscount, self::SalesCancellation => PermissionName::SaleCancel,
            self::ManualJournal, self::AccountTransfer, self::ProfitAllocation => PermissionName::AccountingManage,
            self::Other => PermissionName::ApprovalApprove,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
