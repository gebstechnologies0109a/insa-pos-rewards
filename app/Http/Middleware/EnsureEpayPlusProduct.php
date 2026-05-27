<?php

namespace App\Http\Middleware;

use App\Support\ProductMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEpayPlusProduct
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningInConsole()) {
            return $next($request);
        }

        if (ProductMode::currentProduct($request) !== ProductMode::PRODUCT_EPAYPLUS) {
            abort(404);
        }

        return $next($request);
    }
}
