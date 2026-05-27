<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Maya\MayaCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MayaCheckoutController extends Controller
{
    public function __construct(
        private readonly MayaCheckoutService $checkoutService
    ) {}

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'retailer_id' => ['nullable', 'integer'],
        ]);

        $result = $this->checkoutService->createCheckout($validated);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function webhook(Request $request): JsonResponse
    {
        $this->checkoutService->handleWebhook($request->all());

        return response()->json(['success' => true]);
    }
}
