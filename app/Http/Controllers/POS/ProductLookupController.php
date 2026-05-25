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

        $stockSub = \App\Models\Inventory\StockMovement::selectRaw('product_id, SUM(qty) as total_stock')
            ->where('branch_id', $branchId)
            ->groupBy('product_id');

        $products = $query
            ->leftJoinSub($stockSub, 'stock_agg', 'products.id', '=', 'stock_agg.product_id')
            ->select('products.*', \DB::raw('COALESCE(stock_agg.total_stock, 0) as stock'))
            ->orderBy('products.name')
            ->get();

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
