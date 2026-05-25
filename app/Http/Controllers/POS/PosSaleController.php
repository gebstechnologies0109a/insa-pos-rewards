<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Http\Requests\POS\StoreSaleRequest;
use App\Services\POS\PosSaleService;
use Illuminate\Http\JsonResponse;

class PosSaleController extends Controller
{
    public function __construct(
        protected PosSaleService $service,
    ) {}

    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->service->createSale($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'sale'    => $sale,
        ], 201);
    }
}
