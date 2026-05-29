<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'insa.product'])
                ->prefix('api/pos')
                ->group(base_path('routes/pos/api.php'));

            Route::middleware('epayplus.product')
                ->prefix('api')
                ->group(base_path('routes/epayplus-api.php'));

            Route::middleware('epayplus.product')
                ->prefix('api')
                ->group(base_path('routes/maya-biller.php'));

            Route::middleware('epayplus.product')
                ->post('/api/maya-checkout/webhook', [
                    \App\Http\Controllers\Api\MayaCheckoutController::class,
                    'webhook',
                ]);

            Route::middleware(['web', 'epayplus.product'])
                ->group(base_path('routes/epayplus-web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'audit' => \App\Http\Middleware\AuditLogger::class,
            'epayplus.product' => \App\Http\Middleware\EnsureEpayPlusProduct::class,
            'insa.product' => \App\Http\Middleware\EnsureInsaProduct::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            return route('login', login_redirect_params($request));
        });

        $middleware->validateCsrfTokens(except: [
            'api/pos',
            'api/pos/*',
            'api/pos/device-log',
            'api/pos/device-log/*',
            'api/pos/ping',
            'api/pos/sync/*',
            'api/pos/terminal/*',
            'api/v2/*',
            'api/maya-biller',
            'api/maya-biller/*',
            'api/maya-checkout/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
