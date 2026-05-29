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
use App\Http\Controllers\Backoffice\ReportController as BackofficeReportController;
use App\Http\Controllers\Owner\OwnerController;
use App\Http\Controllers\Backoffice\ShiftAuditController;
use App\Http\Controllers\Backoffice\ShiftDashboardController;
use App\Http\Controllers\Backoffice\ShiftExportController;
use App\Http\Controllers\Backoffice\ShiftManagementController;
use App\Http\Controllers\Backoffice\ShiftVarianceController;
use App\Http\Controllers\POS\CashierController;
use App\Http\Controllers\POS\PosSettingsController;
use App\Http\Controllers\POS\CustomerDisplaySettingsController;
use App\Http\Controllers\POS\ReadingController;
use App\Http\Controllers\Admin\PosSessionController;
use App\Http\Controllers\Stockman\StockmanController;
use App\Http\Controllers\SuperAdmin\BranchController as SuperAdminBranchController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\DeviceController;
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
    Route::redirect('/pos', '/pos/cashier')->name('pos.home');
    Route::get('/pos/cashier', [CashierController::class, 'index'])->name('pos.cashier');
});

// ── Stockman (stockman, manager, admin, owner) ───
Route::middleware(['auth', 'role:stockman,manager,admin,owner,super_admin'])->prefix('stockman')->group(function () {
    Route::get('/inventory', [StockmanController::class, 'inventory'])->name('stockman.inventory');
    Route::get('/audit', [StockmanController::class, 'audit'])->name('stockman.audit');
    Route::post('/audit', [StockmanController::class, 'auditUpdate'])->name('stockman.audit.update');
    Route::get('/stock-in', [StockmanController::class, 'stockInForm'])->name('stockman.stock-in');
    Route::post('/stock-in', [StockmanController::class, 'stockInStore'])->name('stockman.stock-in.store');
    Route::get('/products/search', [StockmanController::class, 'productSearch'])->name('stockman.products.search');
});

// ── Owner console (owner, super_admin) ───────────
Route::middleware(['auth', 'role:owner,super_admin', 'audit:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/', [OwnerController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard', [OwnerController::class, 'dashboard']);
});

// ── Back-Office (manager, admin, owner) ──────────
Route::middleware(['auth', 'role:owner,admin,manager', 'audit:backoffice'])->group(function () {
    Route::get('/backoffice', [DashboardController::class, 'index'])
        ->name('backoffice.dashboard');

    Route::get('/backoffice/analytics', [AnalyticsController::class, 'index'])
        ->name('backoffice.analytics');
    Route::get('/backoffice/analytics/data', [AnalyticsController::class, 'data'])
        ->name('backoffice.analytics.data');
    Route::get('/backoffice/analytics/product/{product}', [AnalyticsController::class, 'productDetail'])
        ->name('backoffice.analytics.product');

    Route::prefix('backoffice/reports')->name('backoffice.reports.')->group(function () {
        Route::get('daily-sales', [BackofficeReportController::class, 'dailySales'])->name('daily-sales');
        Route::get('product-performance', [BackofficeReportController::class, 'productPerformance'])->name('product-performance');
    });

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
    Route::get('/backoffice/readings/x/{xReading}', [ReadingController::class, 'viewXReading'])
        ->name('readings.x.show');
    Route::get('/backoffice/readings/z', [ReadingController::class, 'showZReading'])
        ->name('readings.z');
    Route::post('/backoffice/readings/z/generate', [ReadingController::class, 'storeZReading'])
        ->name('readings.z.generate');
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

    Route::get('pos-sessions', [PosSessionController::class, 'index'])
        ->name('admin.pos-sessions.index');
    Route::post('pos-sessions/{session}/end', [PosSessionController::class, 'end'])
        ->name('admin.pos-sessions.end');

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

// Customer display — editable from cashier gear on POS device
Route::middleware(['auth', 'role:cashier,manager,admin,owner,super_admin'])->group(function () {
    Route::get('/settings/customer-display', [CustomerDisplaySettingsController::class, 'show'])
        ->name('pos.customer-display.show');
    Route::post('/settings/customer-display/photo', [CustomerDisplaySettingsController::class, 'uploadPhoto'])
        ->name('pos.customer-display.photo');
    Route::post('/settings/customer-display/video', [CustomerDisplaySettingsController::class, 'uploadVideo'])
        ->name('pos.customer-display.video');
    Route::post('/settings/customer-display/update', [CustomerDisplaySettingsController::class, 'update'])
        ->name('pos.customer-display.update');
});

Route::get('/customer-display/media/{type}/{filename}', [CustomerDisplaySettingsController::class, 'serveMedia'])
    ->where('type', 'photos|videos')
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('pos.customer-display.media');

// ── Super Admin ──────────────────────────────────
Route::middleware(['auth', 'role:super_admin', 'audit:super-admin'])->prefix('super-admin')->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])
        ->name('super-admin.dashboard');

    Route::get('/licenses', [LicenseController::class, 'index'])
        ->name('super-admin.licenses.index');
    Route::post('/licenses', [LicenseController::class, 'store'])
        ->name('super-admin.licenses.store');
    Route::put('/licenses/{branch}', [LicenseController::class, 'update'])
        ->name('super-admin.licenses.update');

    Route::get('/sessions', [\App\Http\Controllers\SuperAdmin\PosSessionController::class, 'index'])
        ->name('super-admin.sessions.index');
    Route::post('/sessions/{session}/end', [\App\Http\Controllers\SuperAdmin\PosSessionController::class, 'end'])
        ->name('super-admin.sessions.end');

    Route::get('/companies', [CompanyController::class, 'index'])->name('super-admin.companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('super-admin.companies.show');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('super-admin.companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('super-admin.companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('super-admin.companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('super-admin.companies.update');

    Route::get('/branches', [SuperAdminBranchController::class, 'index'])->name('super-admin.branches.index');
    Route::get('/branches/create', [SuperAdminBranchController::class, 'create'])->name('super-admin.branches.create');
    Route::post('/branches', [SuperAdminBranchController::class, 'store'])->name('super-admin.branches.store');
    Route::get('/branches/{branch}', [SuperAdminBranchController::class, 'show'])->name('super-admin.branches.show');
    Route::get('/branches/{branch}/edit', [SuperAdminBranchController::class, 'edit'])->name('super-admin.branches.edit');
    Route::put('/branches/{branch}', [SuperAdminBranchController::class, 'update'])->name('super-admin.branches.update');

    Route::get('/devices', [DeviceController::class, 'index'])->name('super-admin.devices.index');
    Route::get('/devices/create', [DeviceController::class, 'create'])->name('super-admin.devices.create');
    Route::post('/devices', [DeviceController::class, 'store'])->name('super-admin.devices.store');
    Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('super-admin.devices.edit');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('super-admin.devices.update');
});

}); // end insa.product
