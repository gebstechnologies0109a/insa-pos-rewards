<?php

/**
 * ePayPlus Admin Web Routes
 */

use App\Http\Controllers\EPayAdmin\AnnouncementController;
use App\Http\Controllers\EPayAdmin\DashboardController;
use App\Http\Controllers\EPayAdmin\EPayProductController;
use App\Http\Controllers\EPayAdmin\ProviderController;
use App\Http\Controllers\EPayAdmin\ReportController;
use App\Http\Controllers\EPayAdmin\RetailerController;
use App\Http\Controllers\EPayAdmin\SettingController;
use App\Http\Controllers\EPayAdmin\TopupController;
use App\Http\Controllers\EPayAdmin\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:owner,admin,super_admin'])->prefix('epayplus')->group(function () {

    // ── Dashboard ──
    Route::get('/', [DashboardController::class, 'index'])->name('epayplus.dashboard');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData'])->name('epayplus.dashboard.chart');

    // ── Retailers ──
    Route::get('/retailers', [RetailerController::class, 'index'])->name('epayplus.retailers');
    Route::get('/retailers/create', [RetailerController::class, 'create'])->name('epayplus.retailers.create');
    Route::post('/retailers', [RetailerController::class, 'store'])->name('epayplus.retailers.store');
    Route::get('/retailers/{retailer}', [RetailerController::class, 'show'])->name('epayplus.retailers.show');
    Route::get('/retailers/{retailer}/edit', [RetailerController::class, 'edit'])->name('epayplus.retailers.edit');
    Route::put('/retailers/{retailer}', [RetailerController::class, 'update'])->name('epayplus.retailers.update');
    Route::post('/retailers/{retailer}/toggle-status', [RetailerController::class, 'toggleStatus'])->name('epayplus.retailers.toggle-status');
    Route::post('/retailers/{retailer}/adjust-balance', [RetailerController::class, 'adjustBalance'])->name('epayplus.retailers.adjust-balance');
    Route::post('/retailers/{retailer}/reset-pin', [RetailerController::class, 'resetPin'])->name('epayplus.retailers.reset-pin');

    // ── Providers ──
    Route::get('/providers', [ProviderController::class, 'index'])->name('epayplus.providers');
    Route::post('/providers', [ProviderController::class, 'store'])->name('epayplus.providers.store');
    Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('epayplus.providers.update');
    Route::post('/providers/{provider}/toggle-status', [ProviderController::class, 'toggleStatus'])->name('epayplus.providers.toggle-status');

    // ── Products ──
    Route::get('/products', [EPayProductController::class, 'index'])->name('epayplus.products');
    Route::post('/products', [EPayProductController::class, 'store'])->name('epayplus.products.store');
    Route::put('/products/{product}', [EPayProductController::class, 'update'])->name('epayplus.products.update');
    Route::post('/products/{product}/toggle-status', [EPayProductController::class, 'toggleStatus'])->name('epayplus.products.toggle-status');
    Route::get('/products/export', [EPayProductController::class, 'export'])->name('epayplus.products.export');
    Route::post('/products/import', [EPayProductController::class, 'import'])->name('epayplus.products.import');

    // ── Transactions ──
    Route::get('/transactions', [TransactionController::class, 'index'])->name('epayplus.transactions');
    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('epayplus.transactions.export');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('epayplus.transactions.show');
    Route::post('/transactions/{transaction}/update-status', [TransactionController::class, 'updateStatus'])->name('epayplus.transactions.update-status');

    // ── Top-ups ──
    Route::get('/topups', [TopupController::class, 'index'])->name('epayplus.topups');
    Route::post('/topups/{topup}/approve', [TopupController::class, 'approve'])->name('epayplus.topups.approve');
    Route::post('/topups/{topup}/reject', [TopupController::class, 'reject'])->name('epayplus.topups.reject');
    Route::post('/topups/manual-credit', [TopupController::class, 'manualCredit'])->name('epayplus.topups.manual-credit');

    // ── Announcements ──
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('epayplus.announcements');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('epayplus.announcements.store');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('epayplus.announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('epayplus.announcements.destroy');
    Route::post('/announcements/{announcement}/toggle-status', [AnnouncementController::class, 'toggleStatus'])->name('epayplus.announcements.toggle-status');

    // ── Reports ──
    Route::get('/reports', [ReportController::class, 'index'])->name('epayplus.reports');
    Route::get('/reports/daily-sales', [ReportController::class, 'dailySales'])->name('epayplus.reports.daily-sales');
    Route::get('/reports/commissions', [ReportController::class, 'commissions'])->name('epayplus.reports.commissions');
    Route::get('/reports/retailer-performance', [ReportController::class, 'retailerPerformance'])->name('epayplus.reports.retailer-performance');
    Route::get('/reports/provider-performance', [ReportController::class, 'providerPerformance'])->name('epayplus.reports.provider-performance');
    Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('epayplus.reports.export');

    // ── Settings ──
    Route::get('/settings', [SettingController::class, 'index'])->name('epayplus.settings');
    Route::post('/settings', [SettingController::class, 'update'])->name('epayplus.settings.update');

    // ── Audit Log ──
    Route::get('/audit-log', [DashboardController::class, 'auditLog'])->name('epayplus.audit-log');
});
