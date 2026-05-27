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

        if (request()->isSecure()) {
            URL::forceScheme('https');
        }
    }
}
