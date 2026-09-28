<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.login');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

        Route::get('/partners', [BusinessController::class, 'partners'])->name('api.partners.index');
        Route::post('/partners', [BusinessController::class, 'storePartner'])->name('api.partners.store');
        Route::get('/partners/{partner}', [BusinessController::class, 'showPartner'])->name('api.partners.show');
        Route::put('/partners/{partner}', [BusinessController::class, 'updatePartner'])->name('api.partners.update');
        Route::delete('/partners/{partner}', [BusinessController::class, 'destroyPartner'])->name('api.partners.destroy');
        Route::get('/partners/{partner}/statement', [BusinessController::class, 'statement'])->name('api.partners.statement');

        Route::get('/investments', [BusinessController::class, 'investments'])->name('api.investments.index');
        Route::post('/investments', [BusinessController::class, 'storeInvestment'])->name('api.investments.store');
        Route::get('/withdrawals', [BusinessController::class, 'withdrawals'])->name('api.withdrawals.index');
        Route::post('/withdrawals', [BusinessController::class, 'storeWithdrawal'])->name('api.withdrawals.store');

        Route::get('/approvals', [BusinessController::class, 'approvals'])->name('api.approvals.index');
        Route::post('/approvals/{approval}/approve', [BusinessController::class, 'approve'])->middleware('throttle:approvals')->name('api.approvals.approve');
        Route::post('/approvals/{approval}/reject', [BusinessController::class, 'reject'])->middleware('throttle:approvals')->name('api.approvals.reject');

        Route::get('/products', [BusinessController::class, 'products'])->name('api.products.index');
        Route::post('/products', [BusinessController::class, 'storeProduct'])->name('api.products.store');
        Route::get('/purchases', [BusinessController::class, 'purchases'])->name('api.purchases.index');
        Route::post('/purchases', [BusinessController::class, 'storePurchase'])->name('api.purchases.store');
        Route::get('/sales', [BusinessController::class, 'sales'])->name('api.sales.index');
        Route::post('/sales', [BusinessController::class, 'storeSale'])->name('api.sales.store');

        Route::get('/reports/monthly', [BusinessController::class, 'monthly'])->name('api.reports.monthly');
    });
});
