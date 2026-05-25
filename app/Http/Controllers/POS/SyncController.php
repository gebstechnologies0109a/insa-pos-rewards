<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use App\Models\POS\Category;
use App\Models\POS\Customer;
use App\Models\POS\PosSale;
use App\Models\POS\Product;
use App\Services\POS\PosSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected PosSaleService $saleService,
    ) {}

    /**
     * Lightweight connectivity check.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'time'   => now()->toIso8601String(),
        ]);
    }

    /**
     * Push a locally-created transaction to the server.
     * Handles idempotency via local_id.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'local_id'             => 'required|string|max:64',
            'branch_id'            => 'required|integer',
            'shift_id'             => 'nullable|integer',
            'cashier_id'           => 'required|integer',
            'member_id'            => 'nullable|integer',
            'payment_method'       => 'required|string|in:cash,debit_card,credit_card,gcash,maya,palawanpay,other',
            'amount_tendered'      => 'required|numeric|min:0',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.product_name' => 'required|string',
            'items.*.sku'          => 'nullable|string',
            'items.*.barcode'      => 'nullable|string',
            'items.*.qty'          => 'required|numeric|min:0.001',
            'items.*.price'        => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'created_at'           => 'nullable|string',
        ]);

        // Idempotency: check if this local_id was already synced
        $existing = PosSale::where('local_id', $data['local_id'])->first();
        if ($existing) {
            return response()->json([
                'success'   => true,
                'duplicate' => true,
                'server_id' => $existing->id,
                'sale'      => $existing->load('items'),
            ]);
        }

        // Check for stock/price conflicts
        $conflicts = [];
        foreach ($data['items'] as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) continue;

            if (abs((float) $product->price - (float) $item['price']) > 0.01) {
                $conflicts[] = [
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'field'        => 'price',
                    'local_value'  => $item['price'],
                    'server_value' => $product->price,
                ];
            }
        }

        if (!empty($conflicts)) {
            return response()->json([
                'success'  => false,
                'conflict' => $conflicts,
                'message'  => 'Price conflicts detected. Please review.',
            ]);
        }

        try {
            $sale = $this->saleService->createSale($data);

            // Store the local_id for idempotency
            $sale->update(['local_id' => $data['local_id']]);

            return response()->json([
                'success'   => true,
                'server_id' => $sale->id,
                'sale'      => $sale->load('items'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Return all customers for local cache.
     */
    public function allCustomers(): JsonResponse
    {
        $customers = Customer::select('id', 'uuid', 'card_number', 'first_name', 'last_name', 'phone', 'email', 'loyalty_points', 'status', 'updated_at')
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'uuid'           => $c->uuid,
                'card_number'    => $c->card_number,
                'name'           => "{$c->first_name} {$c->last_name}",
                'first_name'     => $c->first_name,
                'last_name'      => $c->last_name,
                'phone'          => $c->phone,
                'email'          => $c->email,
                'loyalty_points' => $c->loyalty_points,
                'status'         => $c->status,
                'updated_at'     => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['customers' => $customers]);
    }

    /**
     * Pull latest product and customer data for local cache.
     */
    public function pull(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $since = $request->input('since');

        $query = Product::with('category')->where('active', true);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        if ($branchId) {
            $stockSub = StockMovement::selectRaw('product_id, SUM(qty) as total_stock')
                ->where('branch_id', $branchId)
                ->groupBy('product_id');
            $query->leftJoinSub($stockSub, 'stock_agg', 'products.id', '=', 'stock_agg.product_id')
                ->select('products.*', \DB::raw('COALESCE(stock_agg.total_stock, 0) as stock'));
        } else {
            $query->select('products.*', \DB::raw('0 as stock'));
        }

        $products = $query->get()->map(function ($p) {
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'sku'         => $p->sku,
                'barcode'     => $p->barcode,
                'price'       => $p->price,
                'category_id' => $p->category_id,
                'category'    => $p->category?->name,
                'stock'       => (float) $p->stock,
                'updated_at'  => $p->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success'    => true,
            'products'   => $products,
            'pulled_at'  => now()->toIso8601String(),
        ]);
    }
}
