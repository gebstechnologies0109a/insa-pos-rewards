<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth'])
                ->prefix('api/pos')
                ->group(base_path('routes/pos/api.php'));

            Route::prefix('api')
                ->group(base_path('routes/epayplus-api.php'));

            Route::prefix('api')
                ->group(base_path('routes/maya-biller.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/epayplus-web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/pos/device-log',
            'api/pos/device-log/*',
            'api/pos/ping',
            'api/pos/sync/*',
            'api/v2/*',
            'api/maya-biller',
            'api/maya-biller/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
