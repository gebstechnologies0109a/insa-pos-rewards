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
        $user = auth()->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $limit = min((int) $request->get('limit', 20), 50);

        $sales = PosSale::where('branch_id', $user->branch_id)
            ->where('cashier_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'sale_number', 'local_id', 'payment_method', 'total', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'sales'   => $sales,
        ]);
    }

    public function receipt(PosSale $sale): JsonResponse
    {
        $user = auth()->user();

        if (! $user || $sale->branch_id !== $user->branch_id) {
            return response()->json(['success' => false, 'message' => 'Sale not found.'], 404);
        }

        $sale->load('items');

        return response()->json([
            'success' => true,
            'receipt' => [
                'sale_number'      => $sale->sale_number,
                'local_id'         => $sale->local_id,
                'created_at'       => $sale->created_at?->toIso8601String(),
                'payment_method'   => $sale->payment_method,
                'subtotal'         => (float) $sale->subtotal,
                'discount_total'   => (float) $sale->discount_total,
                'total'            => (float) $sale->total,
                'amount_tendered'  => (float) $sale->amount_tendered,
                'change_due'       => (float) $sale->change_due,
                'items'            => $sale->items->map(fn ($item) => [
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product_name,
                    'qty'          => (int) $item->qty,
                    'price'        => (float) $item->price,
                    'discount'     => (float) $item->discount,
                ])->values(),
            ],
        ]);
    }
}
