<?php

namespace App\Http\Middleware;

use App\Models\EPayPlus\Retailer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EPayApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token required.',
            ], 401);
        }

        $retailer = Retailer::where('api_token', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (!$retailer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $request->attributes->set('retailer', $retailer);

        return $next($request);
    }
}
