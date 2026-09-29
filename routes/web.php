<?php

use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalSettingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LedgerReportController;
use App\Http\Controllers\ManualJournalController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerFinanceController;
use App\Http\Controllers\PartnerUserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SalesCancellationController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
    Route::resource('partners', PartnerController::class)->except(['create', 'edit']);

    Route::put('/partners/{partner}/user', [PartnerUserController::class, 'update'])->name('partners.user.update');
    Route::delete('/partners/{partner}/user', [PartnerUserController::class, 'destroy'])->name('partners.user.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    Route::resource('investments', InvestmentController::class)->except(['create', 'edit', 'destroy']);
    Route::post('/investments/{investment}/cancel', [InvestmentController::class, 'cancel'])->name('investments.cancel');
    Route::post('/investments/{investment}/reverse', [InvestmentController::class, 'reverse'])->name('investments.reverse');

    Route::resource('withdrawals', WithdrawalController::class)->except(['create', 'edit', 'destroy']);
    Route::post('/withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('withdrawals.cancel');
    Route::post('/withdrawals/{withdrawal}/reverse', [WithdrawalController::class, 'reverse'])->name('withdrawals.reverse');

    Route::resource('transfers', TransferController::class)->except(['create', 'edit', 'destroy']);
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    Route::post('/transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->name('transfers.reverse');

    Route::get('/partners/{partner}/dashboard', [PartnerFinanceController::class, 'dashboard'])->name('partners.dashboard');
    Route::get('/partners/{partner}/statement', [PartnerFinanceController::class, 'statement'])->name('partners.statement');

    Route::get('/accounting/chart', [ChartOfAccountController::class, 'index'])->name('accounting.chart.index');
    Route::post('/accounting/chart', [ChartOfAccountController::class, 'store'])->name('accounting.chart.store');
    Route::put('/accounting/chart/{chart_of_account}', [ChartOfAccountController::class, 'update'])->name('accounting.chart.update');
    Route::delete('/accounting/chart/{chart_of_account}', [ChartOfAccountController::class, 'destroy'])->name('accounting.chart.destroy');

    Route::get('/accounting/accounts', [FinancialAccountController::class, 'index'])->name('accounting.accounts.index');
    Route::post('/accounting/accounts', [FinancialAccountController::class, 'store'])->name('accounting.accounts.store');
    Route::get('/accounting/accounts/{financial_account}', [FinancialAccountController::class, 'show'])->name('accounting.accounts.show');
    Route::put('/accounting/accounts/{financial_account}', [FinancialAccountController::class, 'update'])->name('accounting.accounts.update');
    Route::post('/accounting/accounts/{financial_account}/reconcile', [FinancialAccountController::class, 'reconcile'])->name('accounting.accounts.reconcile');

    Route::get('/accounting/manual-journals', [ManualJournalController::class, 'index'])->name('accounting.manual-journals.index');
    Route::post('/accounting/manual-journals', [ManualJournalController::class, 'store'])->name('accounting.manual-journals.store');
    Route::get('/accounting/manual-journals/{manual_journal}', [ManualJournalController::class, 'show'])->name('accounting.manual-journals.show');
    Route::put('/accounting/manual-journals/{manual_journal}', [ManualJournalController::class, 'update'])->name('accounting.manual-journals.update');
    Route::post('/accounting/manual-journals/{manual_journal}/cancel', [ManualJournalController::class, 'cancel'])->name('accounting.manual-journals.cancel');

    Route::get('/accounting/transfers', [AccountTransferController::class, 'index'])->name('accounting.transfers.index');
    Route::post('/accounting/transfers', [AccountTransferController::class, 'store'])->name('accounting.transfers.store');
    Route::get('/accounting/transfers/{account_transfer}', [AccountTransferController::class, 'show'])->name('accounting.transfers.show');
    Route::post('/accounting/transfers/{account_transfer}/cancel', [AccountTransferController::class, 'cancel'])->name('accounting.transfers.cancel');

    Route::get('/accounting/entries', [JournalEntryController::class, 'index'])->name('accounting.entries.index');
    Route::get('/accounting/entries/{journal_entry}', [JournalEntryController::class, 'show'])->name('accounting.entries.show');
    Route::post('/accounting/entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])->name('accounting.entries.reverse');

    Route::get('/accounting/reports/general-ledger', [LedgerReportController::class, 'generalLedger'])->name('accounting.reports.ledger');
    Route::get('/accounting/reports/cash', [LedgerReportController::class, 'cash'])->name('accounting.reports.cash');
    Route::get('/accounting/reports/bank', [LedgerReportController::class, 'bank'])->name('accounting.reports.bank');

    Route::get('/inventory/catalog', [CatalogController::class, 'index'])->name('inventory.catalog.index');
    Route::post('/inventory/categories', [CatalogController::class, 'storeCategory'])->name('inventory.categories.store');
    Route::post('/inventory/brands', [CatalogController::class, 'storeBrand'])->name('inventory.brands.store');
    Route::post('/inventory/units', [CatalogController::class, 'storeUnit'])->name('inventory.units.store');
    Route::post('/inventory/warehouses', [CatalogController::class, 'storeWarehouse'])->name('inventory.warehouses.store');

    Route::get('/inventory/products', [ProductController::class, 'index'])->name('inventory.products.index');
    Route::post('/inventory/products', [ProductController::class, 'store'])->name('inventory.products.store');
    Route::get('/inventory/products/{product}', [ProductController::class, 'show'])->name('inventory.products.show');
    Route::put('/inventory/products/{product}', [ProductController::class, 'update'])->name('inventory.products.update');

    Route::get('/inventory/suppliers', [SupplierController::class, 'index'])->name('inventory.suppliers.index');
    Route::post('/inventory/suppliers', [SupplierController::class, 'store'])->name('inventory.suppliers.store');
    Route::get('/inventory/suppliers/{supplier}', [SupplierController::class, 'show'])->name('inventory.suppliers.show');
    Route::post('/inventory/suppliers/{supplier}/contacts', [SupplierController::class, 'storeContact'])->name('inventory.suppliers.contacts.store');
    Route::post('/inventory/supplier-payments', [SupplierPaymentController::class, 'store'])->name('inventory.supplier-payments.store');

    Route::get('/inventory/purchases', [PurchaseController::class, 'index'])->name('inventory.purchases.index');
    Route::post('/inventory/purchases', [PurchaseController::class, 'store'])->name('inventory.purchases.store');
    Route::get('/inventory/purchases/{purchase}', [PurchaseController::class, 'show'])->name('inventory.purchases.show');

    Route::get('/inventory/returns', [PurchaseReturnController::class, 'index'])->name('inventory.returns.index');
    Route::post('/inventory/returns', [PurchaseReturnController::class, 'store'])->name('inventory.returns.store');
    Route::get('/inventory/returns/{purchase_return}', [PurchaseReturnController::class, 'show'])->name('inventory.returns.show');

    Route::get('/inventory/stock', [StockController::class, 'index'])->name('inventory.stock.index');
    Route::get('/inventory/stock/movements', [StockController::class, 'movements'])->name('inventory.stock.movements');
    Route::get('/inventory/stock/low', [StockController::class, 'low'])->name('inventory.stock.low');

    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->name('inventory.adjustments.index');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->name('inventory.adjustments.store');

    Route::get('/sales/customers', [CustomerController::class, 'index'])->name('sales.customers.index');
    Route::post('/sales/customers', [CustomerController::class, 'store'])->name('sales.customers.store');
    Route::get('/sales/customers/{customer}', [CustomerController::class, 'show'])->name('sales.customers.show');

    Route::get('/sales/orders', [SaleController::class, 'index'])->name('sales.orders.index');
    Route::post('/sales/orders', [SaleController::class, 'store'])->name('sales.orders.store');
    Route::get('/sales/orders/{sale}', [SaleController::class, 'show'])->name('sales.orders.show');
    Route::post('/sales/payments', [CustomerPaymentController::class, 'store'])->name('sales.payments.store');
    Route::post('/sales/refunds', [RefundController::class, 'store'])->name('sales.refunds.store');
    Route::post('/sales/cancellations', [SalesCancellationController::class, 'store'])->name('sales.cancellations.store');

    Route::get('/sales/returns', [SaleReturnController::class, 'index'])->name('sales.returns.index');
    Route::post('/sales/returns', [SaleReturnController::class, 'store'])->name('sales.returns.store');
    Route::get('/sales/returns/{sale_return}', [SaleReturnController::class, 'show'])->name('sales.returns.show');

    Route::get('/settings/approvals', [ApprovalSettingController::class, 'index'])->name('settings.approvals.index');
    Route::put('/settings/approvals/discount', [ApprovalSettingController::class, 'updateDiscount'])->name('settings.approvals.discount');
    Route::post('/settings/approvals', [ApprovalSettingController::class, 'store'])->name('settings.approvals.store');
    Route::put('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'update'])->name('settings.approvals.update');
    Route::delete('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'destroy'])->name('settings.approvals.destroy');
});
