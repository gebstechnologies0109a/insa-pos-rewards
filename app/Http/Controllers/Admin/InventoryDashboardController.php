<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Category;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryDashboardController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $query = Product::where('active', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(50)->withQueryString();
        $ids = $products->pluck('id')->all();
        $stockMap = $this->inventory->stockTotalsForProducts($branchId, $ids);
        $expiryMap = $this->inventory->earliestExpiryForProducts($branchId, $ids);

        $products->getCollection()->transform(function ($p) use ($stockMap, $expiryMap) {
            $p->stock_on_hand = $stockMap[$p->id] ?? 0;
            $p->earliest_expiry = $expiryMap[$p->id] ?? null;

            return $p;
        });

        if ($stockFilter = $request->input('stock_filter')) {
            $filtered = $products->getCollection()->filter(function ($p) use ($stockFilter) {
                $stock = (float) $p->stock_on_hand;
                if ($stockFilter === 'low') {
                    return $stock > 0 && $stock <= InventoryService::LOW_STOCK_THRESHOLD;
                }
                if ($stockFilter === 'out') {
                    return $stock <= 0;
                }

                return true;
            });
            $products->setCollection($filtered->values());
        }

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        $allIds = Product::where('active', true)->pluck('id')->all();
        $allStock = $this->inventory->stockTotalsForProducts($branchId, $allIds);
        $summary = (object) [
            'total_products' => count($allIds),
            'out_of_stock'   => collect($allStock)->filter(fn ($s) => $s <= 0)->count(),
            'low_stock'      => collect($allStock)->filter(fn ($s) => $s > 0 && $s <= InventoryService::LOW_STOCK_THRESHOLD)->count(),
        ];

        return view('admin.inventory.dashboard', compact('products', 'branches', 'categories', 'branchId', 'summary'));
    }
}
