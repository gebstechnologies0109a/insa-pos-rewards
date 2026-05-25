<?php

use App\Http\Controllers\POS\CustomerLookupController;
use App\Http\Controllers\POS\PosSaleController;
use App\Http\Controllers\POS\PosSettingsController;
use App\Http\Controllers\POS\ProductLookupController;
use App\Http\Controllers\POS\ShiftController;
use App\Http\Controllers\POS\StockInController;
use App\Http\Controllers\POS\SyncController;
use App\Models\Inventory\StockMovement;
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

// ── Sync / Offline ────────────────────────────────────
Route::get('/ping', [SyncController::class, 'ping'])->name('pos.ping')->withoutMiddleware('auth');

Route::prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push'])->name('pos.sync.push');
    Route::get('/pull', [SyncController::class, 'pull'])->name('pos.sync.pull');
});

Route::get('/customers/all', [SyncController::class, 'allCustomers'])->name('pos.customers.all');

Route::prefix('customer')->group(function () {
    Route::post('/lookup', [CustomerLookupController::class, 'lookup'])
        ->name('pos.customer.lookup');
});

Route::post('/sales', [PosSaleController::class, 'store'])
    ->name('pos.sales.store');

Route::post('/stock-in', [StockInController::class, 'store'])
    ->name('pos.stock-in.store');

Route::get('/stock-movements/{product}', function (int $product) {
    return StockMovement::where('product_id', $product)
        ->orderByDesc('created_at')
        ->get();
})->name('pos.stock-movements.index');

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
