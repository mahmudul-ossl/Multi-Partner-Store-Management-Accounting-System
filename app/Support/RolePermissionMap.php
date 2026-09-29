<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;

/**
 * Explicit grant matrix. Super Admin receives every current and future
 * permission added to PermissionName. Other roles are listed on purpose
 * so a new permission is not silently given to operational roles.
 */
final class RolePermissionMap
{
    /**
     * @return list<PermissionName>
     */
    public static function for(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => PermissionName::cases(),
            RoleName::Admin => array_values(array_filter(
                PermissionName::cases(),
                fn (PermissionName $permission): bool => $permission !== PermissionName::RoleManage,
            )),
            RoleName::Accountant => [
                PermissionName::PartnerView,
                PermissionName::PartnerInvestmentView,
                PermissionName::PartnerInvestmentCreate,
                PermissionName::PartnerInvestmentApprove,
                PermissionName::PartnerWithdrawalView,
                PermissionName::PartnerWithdrawalCreate,
                PermissionName::PartnerWithdrawalApprove,
                PermissionName::PartnerTransferView,
                PermissionName::PartnerTransferCreate,
                PermissionName::PartnerTransferApprove,
                PermissionName::PromotionView,
                PermissionName::PromotionApprove,
                PermissionName::ExpenseView,
                PermissionName::ExpenseCreate,
                PermissionName::PurchaseView,
                PermissionName::PurchaseApprove,
                PermissionName::ReportView,
                PermissionName::AccountingView,
                PermissionName::BalanceSheetView,
                PermissionName::ProfitLossView,
                PermissionName::PartnerStatementView,
                PermissionName::ApprovalView,
                PermissionName::ApprovalApprove,
                PermissionName::AuditLogView,
            ],
            RoleName::InventoryManager => [
                PermissionName::PartnerView,
                PermissionName::PurchaseView,
                PermissionName::PurchaseCreate,
                PermissionName::StockView,
                PermissionName::StockAdjust,
                PermissionName::ProductView,
                PermissionName::ProductManage,
                PermissionName::SupplierView,
                PermissionName::SupplierManage,
                PermissionName::ReportView,
            ],
            RoleName::SalesManager => [
                PermissionName::PartnerView,
                PermissionName::SaleView,
                PermissionName::SaleCreate,
                PermissionName::SaleCancel,
                PermissionName::CustomerView,
                PermissionName::CustomerManage,
                PermissionName::PromotionView,
                PermissionName::PromotionCreate,
                PermissionName::ProductView,
                PermissionName::ReportView,
                PermissionName::ProfitLossView,
            ],
            RoleName::Partner => [
                PermissionName::PartnerView,
                PermissionName::PartnerInvestmentView,
                PermissionName::PartnerInvestmentCreate,
                PermissionName::PartnerWithdrawalView,
                PermissionName::PartnerWithdrawalCreate,
                PermissionName::PartnerTransferView,
                PermissionName::PartnerTransferCreate,
                PermissionName::PromotionView,
                PermissionName::PromotionCreate,
                PermissionName::ReportView,
                PermissionName::ProfitLossView,
                PermissionName::PartnerStatementView,
                PermissionName::ApprovalView,
            ],
            RoleName::Viewer => [
                PermissionName::PartnerView,
                PermissionName::PartnerInvestmentView,
                PermissionName::PartnerWithdrawalView,
                PermissionName::PartnerTransferView,
                PermissionName::PromotionView,
                PermissionName::ExpenseView,
                PermissionName::PurchaseView,
                PermissionName::StockView,
                PermissionName::SaleView,
                PermissionName::ProductView,
                PermissionName::ReportView,
                PermissionName::AccountingView,
                PermissionName::BalanceSheetView,
                PermissionName::ProfitLossView,
                PermissionName::PartnerStatementView,
            ],
        };
    }
}
