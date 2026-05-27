<?php

/**
 * ePayPlus API Routes (v2)
 * These endpoints are called by the Android app for authentication,
 * device management, account operations, products, and transactions.
 */

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\AccountController;
use App\Http\Controllers\Api\V2\DeviceFleetApiController;
use App\Http\Controllers\Api\V2\ProductController;
use App\Http\Controllers\Api\V2\TransactionController;
use App\Http\Controllers\Api\V2\WalletController;
use App\Http\Middleware\EPayApiAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {

    // Health check
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]));

    // Public auth routes (no token required)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Authenticated routes
    Route::middleware(EPayApiAuth::class)->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-pin', [AuthController::class, 'changePin']);

        // Account
        Route::get('/account/balance', [AccountController::class, 'balance']);
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::get('/account/profile', [AccountController::class, 'profile']);
        Route::put('/account/profile', [AccountController::class, 'updateProfile']);
        Route::post('/account/topup', [AccountController::class, 'requestTopup']);
        Route::get('/account/topup-history', [AccountController::class, 'topupHistory']);

        // Products
        Route::get('/products/eload', [ProductController::class, 'eloadProducts']);
        Route::get('/products/bills', [ProductController::class, 'billsProducts']);
        Route::get('/products/ecash', [ProductController::class, 'ecashProducts']);
        Route::get('/products/rfid', [ProductController::class, 'rfidProducts']);
        Route::get('/products/providers', [ProductController::class, 'providers']);
        Route::get('/products/announcements', [ProductController::class, 'announcements']);

        // Transactions
        Route::post('/transactions/eload', [TransactionController::class, 'processEload']);
        Route::post('/transactions/bills', [TransactionController::class, 'processBillPayment']);
        Route::post('/transactions/ecash', [TransactionController::class, 'processEcash']);
        Route::post('/transactions/rfid', [TransactionController::class, 'processRfid']);
        Route::get('/transactions/history', [TransactionController::class, 'history']);
        Route::post('/transactions/sync', [TransactionController::class, 'sync']);
    });

    // Device Management (license activation + heartbeat polling)
    Route::post('/device/register', [DeviceApiController::class, 'register']);
    Route::post('/device/heartbeat', [DeviceApiController::class, 'heartbeat']);
    Route::get('/device/config', [DeviceApiController::class, 'getConfig']);
    Route::get('/config', [DeviceApiController::class, 'getConfig']);
    Route::post('/device/log', [DeviceApiController::class, 'log']);
    Route::get('/device/commands', [DeviceApiController::class, 'getCommands']);
    Route::post('/device/command-ack', [DeviceApiController::class, 'acknowledgeCommand']);

    // Transaction Sync (device)
    Route::post('/sync/transactions', [DeviceApiController::class, 'syncTransactions']);
    Route::get('/sync/providers', [DeviceApiController::class, 'getProviders']);
    Route::get('/sync/config', [DeviceApiController::class, 'getSystemConfig']);

    // SMS Reporting
    Route::post('/device/sms-report', [DeviceApiController::class, 'reportSms']);

    // ── Enhanced Device Fleet API ──
    Route::prefix('fleet')->group(function () {
        Route::post('/heartbeat', [DeviceFleetApiController::class, 'heartbeat']);
        Route::get('/commands', [DeviceFleetApiController::class, 'getCommands']);
        Route::post('/command/{commandId}/ack', [DeviceFleetApiController::class, 'acknowledgeCommand']);
        Route::post('/command/{commandId}/result', [DeviceFleetApiController::class, 'commandResult']);
        Route::get('/config', [DeviceFleetApiController::class, 'getConfig']);
        Route::get('/update/check', [DeviceFleetApiController::class, 'checkUpdate']);
        Route::post('/update/result', [DeviceFleetApiController::class, 'reportUpdateResult']);
        Route::post('/alert', [DeviceFleetApiController::class, 'reportAlert']);
    });
});
