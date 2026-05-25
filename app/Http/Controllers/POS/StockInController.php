<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Http\Requests\POS\StoreStockInRequest;
use App\Services\POS\StockInService;
use Illuminate\Http\JsonResponse;

class StockInController extends Controller
{
    public function __construct(
        protected StockInService $service,
    ) {}

    public function store(StoreStockInRequest $request): JsonResponse
    {
        $stockIn = $this->service->create($request->validated());

        return response()->json([
            'success'  => true,
            'stock_in' => $stockIn,
        ], 201);
    }
}
