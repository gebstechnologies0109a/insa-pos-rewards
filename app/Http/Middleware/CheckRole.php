<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login', login_redirect_params($request));
        }

        if ($request->user()->role === 'super_admin') {
            return $next($request);
        }

        if (! empty($roles) && ! in_array($request->user()->role, $roles)) {
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized.');
            }

            $fallback = $request->user()->canAccessBackoffice()
                ? route('backoffice.dashboard')
                : route('login', login_redirect_params($request));

            return redirect($fallback)->with(
                'error',
                'You do not have permission to access that area. Super Admin and Owner accounts only.'
            );
        }

        return $next($request);
    }
}
