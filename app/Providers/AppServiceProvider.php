<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->loadMigrationsFrom(database_path('migrations/epayplus'));

        $this->configureSessionCookiesForRequest();

        if (request()->isSecure()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Keep session cookies compatible with INSA Android WebView (HTTP fallback, no Secure on plain HTTP).
     */
    protected function configureSessionCookiesForRequest(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        if ($request === null) {
            return;
        }

        if (env('SESSION_SECURE_COOKIE') === null) {
            config(['session.secure' => $request->isSecure()]);
        }

        if (is_insa_android_app($request) && ! $request->isSecure()) {
            config(['session.secure' => false]);
        }
    }
}
