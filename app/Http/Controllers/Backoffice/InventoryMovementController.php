<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    use ResolvesInventoryBranch;

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $query = StockMovement::with(['product', 'user', 'batch'])
            ->where('branch_id', $branchId)
            ->orderByDesc('created_at');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $movements = $query->paginate(50)->withQueryString();
        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku']);

        return view('backoffice.inventory.movements', compact('movements', 'branches', 'branchId', 'products'));
    }
}
