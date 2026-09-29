<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalSettingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvestmentController;
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

    Route::get('/settings/approvals', [ApprovalSettingController::class, 'index'])->name('settings.approvals.index');
    Route::post('/settings/approvals', [ApprovalSettingController::class, 'store'])->name('settings.approvals.store');
    Route::put('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'update'])->name('settings.approvals.update');
    Route::delete('/settings/approvals/{threshold}', [ApprovalSettingController::class, 'destroy'])->name('settings.approvals.destroy');
});
