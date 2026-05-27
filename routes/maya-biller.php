<?php

/**
 * Maya Partner Biller API — inbound webhooks from Maya app.
 * CSRF excluded in bootstrap/app.php under api/maya-biller/*
 */

use App\Http\Controllers\Api\MayaBiller\MayaBillerWebhookController;
use App\Http\Middleware\MayaBillerSignatureMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('maya-biller/v1')
    ->middleware(MayaBillerSignatureMiddleware::class)
    ->group(function () {
        Route::post('/validate', [MayaBillerWebhookController::class, 'validatePayment']);
        Route::post('/post', [MayaBillerWebhookController::class, 'postPayment']);
        Route::post('/inquire', [MayaBillerWebhookController::class, 'inquireTransaction']);
        Route::post('/fee', [MayaBillerWebhookController::class, 'getFee']);
    });
