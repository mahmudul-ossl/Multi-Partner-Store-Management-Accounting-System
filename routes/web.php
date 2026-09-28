<?php

use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalSettingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LedgerReportController;
use App\Http\Controllers\ManualJournalController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerFinanceController;
use App\Http\Controllers\PartnerUserController;
use App\Http\Controllers\RoleController;
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

    Route::get('/settings/approvals', [ApprovalSettingController::class, 'index'])->name('settings.approvals.index');
    Route::post('/settings/approvals', [ApprovalSettingController::class, 'store'])->name('settings.approvals.store');
    Route::put('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'update'])->name('settings.approvals.update');
    Route::delete('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'destroy'])->name('settings.approvals.destroy');
});
