<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Full permission catalogue. Later phases reuse these names; do not rename them.
 */
enum PermissionName: string
{
    case PartnerView = 'partner.view';
    case PartnerCreate = 'partner.create';
    case PartnerUpdate = 'partner.update';
    case PartnerDelete = 'partner.delete';

    case PartnerInvestmentView = 'partner.investment.view';
    case PartnerInvestmentCreate = 'partner.investment.create';
    case PartnerInvestmentApprove = 'partner.investment.approve';

    case PartnerWithdrawalView = 'partner.withdrawal.view';
    case PartnerWithdrawalCreate = 'partner.withdrawal.create';
    case PartnerWithdrawalApprove = 'partner.withdrawal.approve';

    case PartnerTransferView = 'partner.transfer.view';
    case PartnerTransferCreate = 'partner.transfer.create';
    case PartnerTransferApprove = 'partner.transfer.approve';

    case PromotionView = 'promotion.view';
    case PromotionCreate = 'promotion.create';
    case PromotionApprove = 'promotion.approve';

    case ExpenseView = 'expense.view';
    case ExpenseCreate = 'expense.create';
    case ExpenseApprove = 'expense.approve';

    case PurchaseView = 'purchase.view';
    case PurchaseCreate = 'purchase.create';
    case PurchaseApprove = 'purchase.approve';

    case StockView = 'stock.view';
    case StockAdjust = 'stock.adjust';
    case StockAdjustApprove = 'stock.adjust.approve';

    case SaleView = 'sale.view';
    case SaleCreate = 'sale.create';
    case SaleCancel = 'sale.cancel';

    case ProductView = 'product.view';
    case ProductManage = 'product.manage';
    case SupplierView = 'supplier.view';
    case SupplierManage = 'supplier.manage';
    case CustomerView = 'customer.view';
    case CustomerManage = 'customer.manage';

    case ReportView = 'report.view';
    case AccountingView = 'accounting.view';
    case AccountingManage = 'accounting.manage';
    case BalanceSheetView = 'balance_sheet.view';
    case ProfitLossView = 'profit_loss.view';
    case PartnerStatementView = 'partner_statement.view';

    case ApprovalView = 'approval.view';
    case ApprovalApprove = 'approval.approve';

    case UserManage = 'user.manage';
    case RoleManage = 'role.manage';
    case SettingsManage = 'settings.manage';
    case AuditLogView = 'audit_log.view';
}
