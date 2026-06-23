<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\VoucherPortalController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Customer portal
Route::get('/', [VoucherPortalController::class, 'index'])->name('portal.index');
Route::post('/purchase', [VoucherPortalController::class, 'purchase'])->name('portal.purchase');
Route::post('/check', [VoucherPortalController::class, 'check'])->name('portal.check');
Route::get('/voucher/{voucher}/status', [VoucherPortalController::class, 'status'])->name('portal.status');

// Auth
Auth::routes(['register' => false]);

// Admin panel
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', PlanController::class);

    Route::resource('vouchers', VoucherController::class);
    Route::post('vouchers/{voucher}/sync', [VoucherController::class, 'syncMikrotik'])->name('vouchers.sync');
    Route::post('vouchers/bulk', [VoucherController::class, 'bulkAction'])->name('vouchers.bulk');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/query', [PaymentController::class, 'queryStatus'])->name('payments.query');
});
