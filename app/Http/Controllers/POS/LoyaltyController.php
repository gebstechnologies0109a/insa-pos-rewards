<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Device-synced loyalty point updates (v3 stub — full rules remain server-side on sale sync).
 */
class LoyaltyController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'points_delta' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        $customer->loyalty_points = max(0, (float) $customer->loyalty_points + (float) $validated['points_delta']);
        $customer->save();

        return response()->json([
            'success' => true,
            'customer_id' => $customer->id,
            'loyalty_points' => (float) $customer->loyalty_points,
        ]);
    }
}
