<?php

/**
 * ePayPlus V2.0 API Routes
 * Server: epayplus.diybizrewards.com
 *
 * Base URL: /api/v2/
 */

use App\Http\Controllers\Api\V2\AccountController;
use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\ProductController;
use App\Http\Controllers\Api\V2\TransactionController;
use App\Http\Middleware\EPayApiAuth;
use Illuminate\Support\Facades\Route;

// ── Public Routes (no auth required) ──────────────────
Route::prefix('v2')->group(function () {

    // Health check
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'ePayPlus API',
        'version' => '2.0.0',
        'timestamp' => now()->toISOString(),
    ]));

    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
});

// ── Protected Routes (token required) ─────────────────
Route::prefix('v2')->middleware(EPayApiAuth::class)->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/account/change-pin', [AuthController::class, 'changePin']);

    // Account
    Route::get('/account/balance', [AccountController::class, 'balance']);
    Route::get('/account/profile', [AccountController::class, 'profile']);
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::post('/account/topup', [AccountController::class, 'requestTopup']);
    Route::get('/account/topup-history', [AccountController::class, 'topupHistory']);

    // Products
    Route::get('/products/eload', [ProductController::class, 'eloadProducts']);
    Route::get('/products/bills', [ProductController::class, 'billsProducts']);
    Route::get('/products/ecash', [ProductController::class, 'ecashProducts']);
    Route::get('/providers', [ProductController::class, 'providers']);

    // Transactions
    Route::post('/transactions/eload', [TransactionController::class, 'processEload']);
    Route::post('/transactions/bills', [TransactionController::class, 'processBillPayment']);
    Route::post('/transactions/ecash', [TransactionController::class, 'processEcash']);
    Route::get('/transactions/history', [TransactionController::class, 'history']);
    Route::post('/transactions/sync', [TransactionController::class, 'sync']);

    // Announcements
    Route::get('/announcements', [ProductController::class, 'announcements']);
});
