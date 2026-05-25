<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Category;
use App\Models\POS\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryDashboardController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id', auth()->user()->branch_id ?? 1);

        $query = Product::select('products.id', 'products.name', 'products.sku', 'products.barcode', 'products.category_id')
            ->leftJoin('stock_movements', function ($join) use ($branchId) {
                $join->on('stock_movements.product_id', '=', 'products.id')
                    ->where('stock_movements.branch_id', $branchId);
            })
            ->where('products.active', true)
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.barcode', 'products.category_id')
            ->selectRaw('COALESCE(SUM(stock_movements.qty), 0) as stock_on_hand');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.sku', 'like', "%{$search}%")
                  ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category')) {
            $query->where('products.category_id', $categoryId);
        }

        $stockFilter = $request->input('stock_filter');
        if ($stockFilter === 'low') {
            $query->havingRaw('COALESCE(SUM(stock_movements.qty), 0) > 0 AND COALESCE(SUM(stock_movements.qty), 0) <= 10');
        } elseif ($stockFilter === 'out') {
            $query->havingRaw('COALESCE(SUM(stock_movements.qty), 0) <= 0');
        }

        $products = $query->orderBy('products.name')->paginate(50)->withQueryString();
        $branches = Branch::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        $summary = DB::table('products')
            ->where('products.active', true)
            ->leftJoin('stock_movements', function ($join) use ($branchId) {
                $join->on('stock_movements.product_id', '=', 'products.id')
                    ->where('stock_movements.branch_id', $branchId);
            })
            ->selectRaw('COUNT(DISTINCT products.id) as total_products')
            ->selectRaw("SUM(CASE WHEN (SELECT COALESCE(SUM(sm2.qty),0) FROM stock_movements sm2 WHERE sm2.product_id = products.id AND sm2.branch_id = ?) <= 0 THEN 1 ELSE 0 END) as out_of_stock", [$branchId])
            ->selectRaw("SUM(CASE WHEN (SELECT COALESCE(SUM(sm3.qty),0) FROM stock_movements sm3 WHERE sm3.product_id = products.id AND sm3.branch_id = ?) > 0 AND (SELECT COALESCE(SUM(sm3.qty),0) FROM stock_movements sm3 WHERE sm3.product_id = products.id AND sm3.branch_id = ?) <= 10 THEN 1 ELSE 0 END) as low_stock", [$branchId, $branchId])
            ->first();

        return view('admin.inventory.dashboard', compact('products', 'branches', 'categories', 'branchId', 'summary'));
    }
}
