<?php

namespace App\Http\Controllers\Stockman;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Category;
use App\Models\POS\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockmanController extends Controller
{
    public function inventory(Request $request)
    {
        $user = auth()->user();
        $branchId = $user->isBranchScoped()
            ? $user->branch_id
            : ($request->input('branch_id', $user->branch_id ?? 1));

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
                  ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('products.name')->paginate(50)->withQueryString();
        $branches = $user->isBranchScoped() ? collect() : Branch::orderBy('name')->get();

        return view('stockman.inventory', compact('products', 'branches', 'branchId'));
    }

    public function stockInForm()
    {
        $user = auth()->user();
        $products = Product::where('active', true)->orderBy('name')->get();
        $branches = $user->isBranchScoped() ? collect() : Branch::orderBy('name')->get();
        $defaultBranchId = $user->branch_id ?? ($branches->first()?->id ?? 1);

        return view('stockman.stock-in', compact('products', 'branches', 'defaultBranchId'));
    }

    public function stockInStore(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'supplier_name'        => 'nullable|string|max:255',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.qty'          => 'required|integer|min:1',
            'items.*.cost'         => 'required|numeric|min:0',
        ];

        if (! $user->isBranchScoped()) {
            $rules['branch_id'] = 'required|exists:branches,id';
        }

        $data = $request->validate($rules);

        $branchId = $user->isBranchScoped()
            ? $user->branch_id
            : $data['branch_id'];

        $result = app(\App\Services\POS\StockInService::class)->create([
            'branch_id'     => $branchId,
            'user_id'       => $user->id,
            'supplier_name' => $data['supplier_name'] ?? null,
            'items'         => collect($data['items'])->map(function ($item) {
                $product = Product::find($item['product_id']);
                return [
                    'product_id'   => $item['product_id'],
                    'product_name' => $product->name,
                    'sku'          => $product->sku,
                    'qty'          => $item['qty'],
                    'cost'         => $item['cost'],
                ];
            })->toArray(),
        ]);

        return redirect()->route('stockman.inventory')
            ->with('success', "Stock-in #{$result->stock_in_number} recorded successfully.");
    }
}
