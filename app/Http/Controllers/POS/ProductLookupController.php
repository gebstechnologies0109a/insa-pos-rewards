<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function all(Request $request): JsonResponse
    {
        $branchId = (int) $request->input('branch_id', auth()->user()?->branch_id ?? 1);
        $categoryId = $request->input('category_id');

        $query = Product::where('active', true);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('products.name')->get();
        $enriched = $this->inventory->enrichProductsWithStock($products, $branchId);

        $categories = \App\Models\POS\Category::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'products'   => $enriched,
            'categories' => $categories,
        ])->header('Cache-Control', 'private, max-age=60');
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $branchId = (int) $request->input('branch_id', auth()->user()?->branch_id ?? 1);

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $exact = Product::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                  ->orWhere('sku', $query);
            })
            ->first();

        if ($exact) {
            $enriched = $this->inventory->enrichProductsWithStock([$exact], $branchId);

            return response()->json($enriched);
        }

        $products = Product::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($this->inventory->enrichProductsWithStock($products, $branchId));
    }
}
