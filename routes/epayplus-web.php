<?php

/**
 * ePayPlus Admin Web Routes
 * For managing retailers, transactions, products via web dashboard
 */

use App\Http\Controllers\EPayAdmin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:owner,admin'])->prefix('epayplus')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('epayplus.dashboard');
    Route::get('/retailers', [DashboardController::class, 'retailers'])->name('epayplus.retailers');
    Route::get('/retailers/{retailer}', [DashboardController::class, 'retailerDetail'])->name('epayplus.retailers.show');
    Route::post('/retailers/add-balance', [DashboardController::class, 'addBalance'])->name('epayplus.retailers.add-balance');

    Route::get('/transactions', [DashboardController::class, 'transactions'])->name('epayplus.transactions');

    Route::post('/topups/{topup}/approve', [DashboardController::class, 'approveTopup'])->name('epayplus.topups.approve');
    Route::post('/topups/{topup}/reject', [DashboardController::class, 'rejectTopup'])->name('epayplus.topups.reject');
});
