<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Http\Requests\POS\StoreSaleRequest;
use App\Models\POS\PosSale;
use App\Services\POS\PosSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function recent(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 20), 50);
        $user = auth()->user();

        $sales = PosSale::where('branch_id', $user->branch_id)
            ->where('cashier_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'sale_number', 'local_id', 'payment_method', 'grand_total as total', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'sales'   => $sales,
        ]);
    }
}
