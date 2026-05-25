<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
    public function all(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', auth()->user()?->branch_id);
        $categoryId = $request->input('category_id');

        $query = Product::where('active', true);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->get()->map(function ($p) use ($branchId) {
            $stock = \App\Models\Inventory\StockMovement::where('product_id', $p->id)
                ->where('branch_id', $branchId)
                ->sum('qty');
            $p->stock = (float) $stock;
            return $p;
        });

        $categories = \App\Models\POS\Category::orderBy('name')->get();

        return response()->json([
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

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
            return response()->json([$exact]);
        }

        $products = Product::where('active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(20)
            ->get();

        return response()->json($products);
    }
}
