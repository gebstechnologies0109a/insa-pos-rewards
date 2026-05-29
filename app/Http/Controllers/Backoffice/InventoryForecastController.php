<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\ForecastingService;
use App\Services\Inventory\InventoryForecastService;
use Illuminate\Http\Request;

class InventoryForecastController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryForecastService $reorderForecast,
        protected ForecastingService $fefoForecast,
    ) {}

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $lookback = min(max((int) $request->input('lookback', 30), 7), 90);
        $cover = min(max((int) $request->input('cover', 14), 7), 60);
        $horizon = min(max((int) $request->input('horizon', 30), 7), 90);

        $rows = $this->reorderForecast->forecastReport($branchId, $lookback, $cover);
        $productDetail = null;
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku']);

        if ($productId = $request->integer('product_id')) {
            $product = $products->firstWhere('id', $productId);
            if ($product) {
                $row = collect($rows)->firstWhere('product_id', $productId);
                $avgDaily = (float) ($row['daily_consumption'] ?? 0);
                $productDetail = $this->fefoForecast->forecast($branchId, $productId, max($avgDaily, 0.01), $horizon);
                $productDetail['name'] = $product->name;
                $productDetail['sku'] = $product->sku;
                $productDetail['suggested_reorder'] = $row['suggested_reorder'] ?? 0;
                $productDetail['daily_consumption'] = $avgDaily;
            }
        }

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.inventory.forecast.index', compact(
            'rows',
            'branches',
            'branchId',
            'lookback',
            'cover',
            'horizon',
            'productDetail',
            'products',
        ));
    }
}
