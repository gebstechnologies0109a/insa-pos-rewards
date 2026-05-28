<?php

use App\Http\Controllers\POS\LicenseValidateController;
use App\Http\Controllers\POS\CustomerLookupController;
use App\Http\Controllers\POS\PosTerminalSessionController;
use App\Http\Controllers\POS\InventoryApiController;
use App\Http\Controllers\POS\PosSaleController;
use App\Http\Controllers\POS\PosSettingsController;
use App\Http\Controllers\POS\ProductLookupController;
use App\Http\Controllers\POS\ReadingController;
use App\Http\Controllers\POS\ShiftController;
use App\Http\Controllers\POS\StockInController;
use App\Http\Controllers\POS\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS API Routes
|--------------------------------------------------------------------------
|
| All POS-specific API endpoints are defined here. These routes are loaded
| with the "web" middleware group and a "/api/pos" prefix.
|
*/

// ── Device Logging (store has no auth — device may not have session) ──
Route::post('/device-log', [\App\Http\Controllers\DeviceLogController::class, 'store'])->name('pos.device-log')->withoutMiddleware('auth');
Route::post('/device-log/clear', [\App\Http\Controllers\DeviceLogController::class, 'clear'])->name('pos.device-log.clear');

// ── Sync / Offline ────────────────────────────────────
Route::get('/ping', [SyncController::class, 'ping'])->name('pos.ping')->withoutMiddleware('auth');

Route::prefix('terminal')->group(function () {
    Route::post('/register', [PosTerminalSessionController::class, 'register'])->name('pos.terminal.register');
    Route::post('/end', [PosTerminalSessionController::class, 'end'])->name('pos.terminal.end');
    Route::get('/status', [PosTerminalSessionController::class, 'status'])->name('pos.terminal.status');
});

Route::prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push'])->name('pos.sync.push');
    Route::get('/pull', [SyncController::class, 'pull'])->name('pos.sync.pull');
});

Route::post('/license/validate', [LicenseValidateController::class, 'validate'])
    ->name('pos.license.validate');

Route::get('/customers/all', [SyncController::class, 'allCustomers'])->name('pos.customers.all');

Route::prefix('customer')->group(function () {
    Route::post('/lookup', [CustomerLookupController::class, 'lookup'])
        ->name('pos.customer.lookup');
    Route::post('/quick-lookup', [CustomerLookupController::class, 'quickLookup'])
        ->name('pos.customer.quick-lookup');
});

Route::post('/sales', [PosSaleController::class, 'store'])
    ->name('pos.sales.store');

Route::get('/sales/recent', [PosSaleController::class, 'recent'])
    ->name('pos.sales.recent');

Route::get('/sales/{sale}/receipt', [PosSaleController::class, 'receipt'])
    ->name('pos.sales.receipt');

Route::post('/stock-in', [StockInController::class, 'store'])
    ->name('pos.stock-in.store');

Route::get('/stock-movements/{product}', [InventoryApiController::class, 'movements'])
    ->name('pos.stock-movements.index');

Route::prefix('inventory')
    ->middleware('role:cashier,manager,admin,owner,super_admin,stockman')
    ->group(function () {
        Route::get('/batches', [InventoryApiController::class, 'batches'])->name('pos.inventory.batches');
        Route::get('/expiry', [InventoryApiController::class, 'expiry'])->name('pos.inventory.expiry');
        Route::post('/adjustments', [InventoryApiController::class, 'adjustments'])->name('pos.inventory.adjustments');
        Route::post('/adjust', [InventoryApiController::class, 'adjustments'])->name('pos.inventory.adjust');
        Route::post('/audit', [InventoryApiController::class, 'audit'])->name('pos.inventory.audit');
    });

Route::get('/products/search', [ProductLookupController::class, 'search'])
    ->name('pos.products.search');

Route::get('/products/all', [ProductLookupController::class, 'all'])
    ->name('pos.products.all');

Route::get('/settings', [PosSettingsController::class, 'apiIndex'])
    ->name('pos.settings.api');

Route::prefix('shift')->group(function () {
    Route::get('/current', [ShiftController::class, 'current'])->name('pos.shift.current');
    Route::post('/open', [ShiftController::class, 'open'])->name('pos.shift.open');
    Route::post('/close', [ShiftController::class, 'close'])->name('pos.shift.close');
});

// ── X/Z Readings ──────────────────────────────────────
Route::post('/x-reading', [ReadingController::class, 'generateXReading'])->name('pos.x-reading');
Route::post('/z-reading', [ReadingController::class, 'generateZReading'])->name('pos.z-reading');
