<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryDashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportExportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Backoffice\AnalyticsController;
use App\Http\Controllers\Backoffice\ExpiryDashboardController;
use App\Http\Controllers\Backoffice\InventoryAdjustmentController;
use App\Http\Controllers\Backoffice\InventoryBatchController;
use App\Http\Controllers\Backoffice\InventoryMovementController;
use App\Http\Controllers\Backoffice\InventoryReportController;
use App\Http\Controllers\Backoffice\ShiftAuditController;
use App\Http\Controllers\Backoffice\ShiftDashboardController;
use App\Http\Controllers\Backoffice\ShiftExportController;
use App\Http\Controllers\Backoffice\ShiftManagementController;
use App\Http\Controllers\Backoffice\ShiftVarianceController;
use App\Http\Controllers\POS\PosSettingsController;
use App\Http\Controllers\POS\ReadingController;
use App\Http\Controllers\Stockman\StockmanController;
use App\Http\Controllers\SuperAdmin\BranchOverviewController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\LicenseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// ── Auth ─────────────────────────────────────────
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── INSA POS (blocked on ePayPlus host / APP_PRODUCT=epayplus) ─
Route::middleware('insa.product')->group(function () {

// ── POS Cashier (cashier, manager, admin, owner) ─
Route::middleware(['auth', 'role:cashier,manager,admin,owner'])->group(function () {
    Route::get('/pos/cashier', function () {
        return view('pos.cashier.index');
    })->name('pos.cashier');
});

// ── Stockman (stockman, manager, admin, owner) ───
Route::middleware(['auth', 'role:stockman,manager,admin,owner'])->prefix('stockman')->group(function () {
    Route::get('/inventory', [StockmanController::class, 'inventory'])->name('stockman.inventory');
    Route::get('/audit', [StockmanController::class, 'audit'])->name('stockman.audit');
    Route::post('/audit', [StockmanController::class, 'auditUpdate'])->name('stockman.audit.update');
    Route::get('/stock-in', [StockmanController::class, 'stockInForm'])->name('stockman.stock-in');
    Route::post('/stock-in', [StockmanController::class, 'stockInStore'])->name('stockman.stock-in.store');
});

// ── Back-Office (manager, admin, owner) ──────────
Route::middleware(['auth', 'role:owner,admin,manager'])->group(function () {
    Route::get('/backoffice', [DashboardController::class, 'index'])
        ->name('backoffice.dashboard');

    Route::get('/backoffice/analytics', [AnalyticsController::class, 'index'])
        ->name('backoffice.analytics');
    Route::get('/backoffice/analytics/data', [AnalyticsController::class, 'data'])
        ->name('backoffice.analytics.data');
    Route::get('/backoffice/analytics/product/{product}', [AnalyticsController::class, 'productDetail'])
        ->name('backoffice.analytics.product');

    Route::get('/backoffice/shifts', [ShiftManagementController::class, 'index'])
        ->name('backoffice.shifts');

    Route::get('/backoffice/shifts/dashboard', [ShiftDashboardController::class, 'index'])
        ->name('backoffice.shifts.dashboard');

    Route::get('/backoffice/shifts/variance', [ShiftVarianceController::class, 'index'])
        ->name('backoffice.shifts.variance');

    Route::get('/backoffice/shifts/audit', [ShiftAuditController::class, 'index'])
        ->name('backoffice.shifts.audit');

    Route::get('/backoffice/shifts/{shift}', [ShiftExportController::class, 'show'])
        ->name('backoffice.shifts.show');

    Route::get('/backoffice/shifts/{shift}/export/csv', [ShiftExportController::class, 'exportCsv'])
        ->name('backoffice.shifts.export.csv');

    Route::get('/backoffice/shifts/{shift}/export/pdf', [ShiftExportController::class, 'exportPdf'])
        ->name('backoffice.shifts.export.pdf');

    Route::get('/shifts/{shift}/export/csv', [ShiftExportController::class, 'exportCsv'])
        ->name('shift.export.csv');

    Route::get('/shifts/{shift}/export/pdf', [ShiftExportController::class, 'exportPdf'])
        ->name('shift.export.pdf');

    // ── X/Z Reading Reports ──
    Route::get('/backoffice/readings/x', [ReadingController::class, 'showXReading'])
        ->name('readings.x');
    Route::get('/backoffice/readings/z', [ReadingController::class, 'showZReading'])
        ->name('readings.z');
    Route::get('/backoffice/readings/x/export/csv', [ReadingController::class, 'exportXReadingCsv'])
        ->name('readings.x.export.csv');
    Route::get('/backoffice/readings/z/export/csv', [ReadingController::class, 'exportZReadingCsv'])
        ->name('readings.z.export.csv');

    Route::prefix('backoffice/inventory')->name('backoffice.inventory.')->group(function () {
        Route::get('batches', [InventoryBatchController::class, 'index'])->name('batches');
        Route::get('batches/{batch}/edit', [InventoryBatchController::class, 'edit'])->name('batches.edit');
        Route::put('batches/{batch}', [InventoryBatchController::class, 'update'])->name('batches.update');
        Route::post('batches/{batch}/adjust', [InventoryBatchController::class, 'adjust'])->name('batches.adjust');

        Route::get('adjustment', [InventoryAdjustmentController::class, 'create'])->name('adjustment');
        Route::post('adjustment', [InventoryAdjustmentController::class, 'store'])->name('adjustment.store');

        Route::get('movements', [InventoryMovementController::class, 'index'])->name('movements');

        Route::get('expiry', [ExpiryDashboardController::class, 'index'])->name('expiry');
        Route::post('expiry/{alert}/handle', [ExpiryDashboardController::class, 'handle'])->name('expiry.handle');
        Route::post('expiry/{alert}/snooze', [ExpiryDashboardController::class, 'snooze'])->name('expiry.snooze');

        Route::get('report', [InventoryReportController::class, 'inventory'])->name('report');
        Route::get('forecast', [InventoryReportController::class, 'forecast'])->name('forecast');
    });
});

Route::middleware(['auth', 'role:owner,admin,manager'])->prefix('admin')->group(function () {
    Route::resource('products', ProductController::class)
        ->names('admin.products')
        ->except(['show']);

    Route::get('products-export', [ProductImportExportController::class, 'export'])
        ->name('admin.products.export');
    Route::post('products-import', [ProductImportExportController::class, 'import'])
        ->name('admin.products.import');

    Route::resource('categories', CategoryController::class)
        ->names('admin.categories')
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('inventory', [InventoryDashboardController::class, 'index'])
        ->name('admin.inventory.dashboard');
});

// ── Admin-only (owner, admin) ────────────────────
// ── Device Logs (admin/owner only) ────────────────
Route::middleware(['auth', 'role:owner,admin'])->group(function () {
    Route::get('/insaposlogs', [\App\Http\Controllers\DeviceLogController::class, 'index'])
        ->name('admin.device-logs');
});

Route::middleware(['auth', 'role:owner,admin'])->prefix('admin')->group(function () {
    Route::resource('branches', BranchController::class)
        ->names('admin.branches')
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('branches/assign', [BranchController::class, 'assign'])
        ->name('admin.branches.assign');

    Route::resource('users', UserManagementController::class)
        ->names('admin.users')
        ->except(['show']);
});

// ── Settings (owner, admin can edit; manager view-only) ──
Route::middleware(['auth', 'role:owner,admin,manager'])->group(function () {
    Route::get('/pos/settings', [PosSettingsController::class, 'index'])
        ->name('pos.settings');
});

Route::middleware(['auth', 'role:owner,admin'])->group(function () {
    Route::post('/pos/settings', [PosSettingsController::class, 'update'])
        ->name('pos.settings.update');
});

// ── Super Admin ──────────────────────────────────
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])
        ->name('super-admin.dashboard');

    Route::get('/licenses', [LicenseController::class, 'index'])
        ->name('super-admin.licenses.index');
    Route::post('/licenses', [LicenseController::class, 'store'])
        ->name('super-admin.licenses.store');
    Route::put('/licenses/{branch}', [LicenseController::class, 'update'])
        ->name('super-admin.licenses.update');

    Route::get('/branches', [BranchOverviewController::class, 'index'])
        ->name('super-admin.branches.index');
    Route::get('/branches/{branch}', [BranchOverviewController::class, 'show'])
        ->name('super-admin.branches.show');
});

}); // end insa.product
