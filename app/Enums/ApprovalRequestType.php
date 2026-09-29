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
    case StockAdjustment = 'stock_adjustment';
    case Refund = 'refund';
    case LargeDiscount = 'large_discount';
    case SalesCancellation = 'sales_cancellation';
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
            self::StockAdjustment => 'Stock adjustment',
            self::Refund => 'Refund',
            self::LargeDiscount => 'Large discount',
            self::SalesCancellation => 'Sales cancellation',
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
            self::StockAdjustment => PermissionName::StockAdjustApprove,
            self::Refund, self::LargeDiscount, self::SalesCancellation => PermissionName::SaleCancel,
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
