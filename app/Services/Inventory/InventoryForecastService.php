<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockMovement;
use App\Models\POS\Product;
use Carbon\Carbon;

class InventoryForecastService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forecastReport(int $branchId, int $lookbackDays = 30, int $reorderCoverDays = 14): array
    {
        $since = now()->subDays($lookbackDays)->startOfDay();

        $consumption = StockMovement::where('branch_id', $branchId)
            ->where('type', 'sale')
            ->where('created_at', '>=', $since)
            ->selectRaw('product_id, SUM(ABS(qty)) as sold')
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        $products = Product::where('active', true)->orderBy('name')->get();
        $stockMap = $this->inventory->stockTotalsForProducts($branchId, $products->pluck('id')->all());

        return $products->map(function (Product $product) use ($consumption, $stockMap, $lookbackDays, $reorderCoverDays) {
            $sold = (float) ($consumption[$product->id] ?? 0);
            $daily = $lookbackDays > 0 ? $sold / $lookbackDays : 0.0;
            $stock = (float) ($stockMap[$product->id] ?? 0);
            $daysToZero = $daily > 0 ? round($stock / $daily, 1) : null;
            $target = $daily * $reorderCoverDays;
            $suggested = max(0, round($target - $stock, 3));

            return [
                'product_id'        => $product->id,
                'name'              => $product->name,
                'sku'               => $product->sku,
                'current_stock'     => $stock,
                'sold_period'       => $sold,
                'daily_consumption' => round($daily, 3),
                'days_to_zero'      => $daysToZero,
                'suggested_reorder' => $suggested,
            ];
        })->filter(fn ($row) => $row['sold_period'] > 0 || $row['current_stock'] > 0)->values()->all();
    }

    /**
     * Products with on-hand stock but no sale movements in the lookback window.
     *
     * @return array<int, array{product: Product, stock: float, last_sale_at: ?string}>
     */
    public function slowMovingProducts(int $branchId, int $noSaleDays = 60): array
    {
        $since = now()->subDays($noSaleDays)->startOfDay();

        $recentSales = StockMovement::where('branch_id', $branchId)
            ->where('type', 'sale')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('product_id')
            ->all();

        $products = Product::where('active', true)->orderBy('name')->get();
        $stockMap = $this->inventory->stockTotalsForProducts($branchId, $products->pluck('id')->all());

        $lastSales = StockMovement::where('branch_id', $branchId)
            ->where('type', 'sale')
            ->selectRaw('product_id, MAX(created_at) as last_sale_at')
            ->groupBy('product_id')
            ->pluck('last_sale_at', 'product_id');

        $rows = [];
        foreach ($products as $product) {
            $stock = (float) ($stockMap[$product->id] ?? 0);
            if ($stock <= 0 || in_array($product->id, $recentSales, true)) {
                continue;
            }

            $last = $lastSales[$product->id] ?? null;
            $rows[] = [
                'product'      => $product,
                'stock'        => $stock,
                'last_sale_at' => $last ? Carbon::parse($last)->toDateString() : null,
            ];
        }

        return $rows;
    }

    /**
     * Batches with stock that expire after the near window (used for slow-moving expiry context).
     *
     * @return \Illuminate\Support\Collection<int, InventoryBatch>
     */
    public function slowMovingBatches(int $branchId, int $noSaleDays = 60)
    {
        $productIds = collect($this->slowMovingProducts($branchId, $noSaleDays))
            ->pluck('product.id')
            ->filter()
            ->all();

        if ($productIds === []) {
            return collect();
        }

        return InventoryBatch::with(['product'])
            ->forBranch($branchId)
            ->whereIn('product_id', $productIds)
            ->withStock()
            ->orderBy('expiry_date')
            ->get();
    }
}
