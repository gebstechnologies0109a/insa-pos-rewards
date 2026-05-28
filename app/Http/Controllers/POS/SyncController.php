<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Category;
use App\Services\Inventory\InventoryService;
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
        protected InventoryService $inventory,
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

        $productModels = $query->get();

        if ($branchId) {
            $enriched = $this->inventory->enrichProductsWithStock($productModels, (int) $branchId);
        } else {
            $enriched = $productModels->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'sku'         => $p->sku,
                'barcode'     => $p->barcode,
                'price'       => $p->price,
                'category_id' => $p->category_id,
                'category'    => $p->category?->name,
                'stock'       => 0,
                'updated_at'  => $p->updated_at?->toIso8601String(),
            ])->all();
        }

        $products = collect($enriched)->map(function ($p) {
            $row = is_array($p) ? $p : (array) $p;

            return [
                'id'              => $row['id'],
                'name'            => $row['name'],
                'sku'             => $row['sku'] ?? null,
                'barcode'         => $row['barcode'] ?? null,
                'price'           => $row['price'],
                'category_id'     => $row['category_id'] ?? null,
                'category'        => $row['category'] ?? null,
                'stock'           => (float) ($row['stock'] ?? 0),
                'earliest_expiry' => $row['earliest_expiry'] ?? null,
                'near_expiry'     => (bool) ($row['near_expiry'] ?? false),
                'low_stock'       => (bool) ($row['low_stock'] ?? false),
                'updated_at'      => $row['updated_at'] ?? null,
            ];
        });

        $categories = Category::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('name')
            ->get(['id', 'name', 'updated_at']);

        return response()->json([
            'success'    => true,
            'products'   => $products,
            'categories' => $categories,
            'pulled_at'  => now()->toIso8601String(),
        ]);
    }
}
