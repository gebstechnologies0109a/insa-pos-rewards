<?php

/**
 * ePayPlus Device API Routes (v2)
 * These endpoints are called by the Android app for device management,
 * configuration sync, and transaction processing.
 */

use App\Http\Controllers\Api\DeviceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {

    // Device Management
    Route::post('/device/register', [DeviceApiController::class, 'register']);
    Route::post('/device/heartbeat', [DeviceApiController::class, 'heartbeat']);
    Route::get('/device/config', [DeviceApiController::class, 'getConfig']);
    Route::post('/device/log', [DeviceApiController::class, 'log']);
    Route::get('/device/commands', [DeviceApiController::class, 'getCommands']);
    Route::post('/device/command-ack', [DeviceApiController::class, 'acknowledgeCommand']);

    // Transaction Sync
    Route::post('/sync/transactions', [DeviceApiController::class, 'syncTransactions']);
    Route::get('/sync/providers', [DeviceApiController::class, 'getProviders']);
    Route::get('/sync/config', [DeviceApiController::class, 'getSystemConfig']);

    // SMS Reporting
    Route::post('/device/sms-report', [DeviceApiController::class, 'reportSms']);
});
