<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function inventory(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $products = Product::where('active', true)->orderBy('name')->get();
        $ids = $products->pluck('id')->all();
        $stockMap = $this->inventory->stockTotalsForProducts($branchId, $ids);
        $expiryMap = $this->inventory->earliestExpiryForProducts($branchId, $ids);

        $rows = $products->map(fn (Product $p) => [
            'product'         => $p,
            'stock'           => $stockMap[$p->id] ?? 0,
            'earliest_expiry' => $expiryMap[$p->id] ?? null,
            'low_stock'       => ($stockMap[$p->id] ?? 0) > 0 && ($stockMap[$p->id] ?? 0) <= InventoryService::LOW_STOCK_THRESHOLD,
        ]);

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.inventory.report-inventory', compact('rows', 'branches', 'branchId'));
    }

    public function forecast(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $lookback = min(max((int) $request->input('lookback', 30), 7), 90);
        $cover = min(max((int) $request->input('cover', 14), 7), 60);

        $rows = $this->inventory->forecastReport($branchId, $lookback, $cover);
        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.inventory.report-forecast', compact('rows', 'branches', 'branchId', 'lookback', 'cover'));
    }
}
