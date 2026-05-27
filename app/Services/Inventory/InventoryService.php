<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockMovement;
use App\Models\POS\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryService
{
    public const LOW_STOCK_THRESHOLD = 10;

    public const NEAR_EXPIRY_DAYS = 7;

    public function getStockOnHand(int $branchId, int $productId): float
    {
        if (! $this->batchInventoryEnabled()) {
            return (float) StockMovement::where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->sum('qty');
        }

        $batchStock = (float) InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->notExpired()
            ->sum('quantity');

        if (InventoryBatch::forBranch($branchId)->forProduct($productId)->exists()) {
            return $batchStock;
        }

        return (float) StockMovement::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('qty');
    }

    public function earliestExpiryDate(int $branchId, int $productId): ?string
    {
        $date = InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->withStock()
            ->notExpired()
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->value('expiry_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    public function hasNearExpiry(int $branchId, int $productId, int $withinDays = self::NEAR_EXPIRY_DAYS): bool
    {
        $cutoff = now()->addDays($withinDays)->toDateString();

        return InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->withStock()
            ->notExpired()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->exists();
    }

    /**
     * @param  array<int, array{product_id:int,qty:float,cost?:float,expiry_date?:?string,batch_code?:?string}>  $items
     * @return array<int, InventoryBatch>
     */
    public function stockIn(
        int $branchId,
        array $items,
        string $type = 'stock_in',
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $userId = null,
        ?string $supplierName = null,
    ): array {
        if (! $this->batchInventoryEnabled()) {
            foreach ($items as $item) {
                $this->recordMovement(
                    branchId: $branchId,
                    productId: $item['product_id'],
                    qty: (float) $item['qty'],
                    type: $type,
                    referenceId: $referenceId,
                    referenceNumber: $referenceNumber,
                    userId: $userId,
                );
            }

            return [];
        }

        $batches = [];

        foreach ($items as $item) {
            $batch = InventoryBatch::create(
                $this->filterBatchAttributes([
                    'branch_id'     => $branchId,
                    'product_id'    => $item['product_id'],
                    'batch_code'    => $item['batch_code'] ?? null,
                    'expiry_date'   => $item['expiry_date'] ?? null,
                    'quantity'      => $item['qty'],
                    'cost_price'    => $item['cost'] ?? null,
                    'supplier_name' => $supplierName,
                    'received_at'   => now(),
                ]),
            );

            $this->recordMovement(
                branchId: $branchId,
                productId: $item['product_id'],
                qty: (float) $item['qty'],
                type: $type,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                userId: $userId,
                batchId: $batch->id,
            );

            $batches[] = $batch;
        }

        return $batches;
    }

    /**
     * FEFO stock deduction. Returns batch allocations used.
     *
     * @return array<int, array{batch_id:?int,qty:float}>
     */
    public function stockOut(
        int $branchId,
        int $productId,
        float $qty,
        string $type,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $userId = null,
        ?int $shiftId = null,
        ?string $reason = null,
    ): array {
        if ($qty <= 0) {
            return [];
        }

        $available = $this->getStockOnHand($branchId, $productId);
        if ($available < $qty) {
            throw new \RuntimeException("Insufficient stock for product ID {$productId}");
        }

        $batches = InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->withStock()
            ->notExpired()
            ->fefoOrder()
            ->lockForUpdate()
            ->get();

        if ($batches->isEmpty()) {
            $this->recordMovement(
                branchId: $branchId,
                productId: $productId,
                qty: -1 * $qty,
                type: $type,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                userId: $userId,
                shiftId: $shiftId,
                reason: $reason,
            );

            return [['batch_id' => null, 'qty' => $qty]];
        }

        $remaining = $qty;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $batch->quantity, $remaining);
            $batch->decrement('quantity', $take);

            $this->recordMovement(
                branchId: $branchId,
                productId: $productId,
                qty: -1 * $take,
                type: $type,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                userId: $userId,
                shiftId: $shiftId,
                batchId: $batch->id,
                reason: $reason,
            );

            $allocations[] = ['batch_id' => $batch->id, 'qty' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0.0001) {
            throw new \RuntimeException("Insufficient batch stock for product ID {$productId}");
        }

        return $allocations;
    }

    public function adjustBatch(
        int $batchId,
        float $newQuantity,
        string $reason,
        int $userId,
        ?int $shiftId = null,
    ): InventoryBatch {
        return DB::transaction(function () use ($batchId, $newQuantity, $reason, $userId, $shiftId) {
            $batch = InventoryBatch::lockForUpdate()->findOrFail($batchId);
            $delta = $newQuantity - (float) $batch->quantity;

            if (abs($delta) < 0.0001) {
                return $batch;
            }

            $batch->update(['quantity' => max(0, $newQuantity)]);

            $this->recordMovement(
                branchId: $batch->branch_id,
                productId: $batch->product_id,
                qty: $delta,
                type: 'adjustment',
                referenceId: $batch->id,
                referenceNumber: $batch->batch_code ?? 'BATCH-' . $batch->id,
                userId: $userId,
                shiftId: $shiftId,
                batchId: $batch->id,
                reason: $reason,
            );

            return $batch->fresh();
        });
    }

    public function adjustProduct(
        int $branchId,
        int $productId,
        float $targetQty,
        string $reason,
        int $userId,
    ): void {
        $current = $this->getStockOnHand($branchId, $productId);
        $delta = $targetQty - $current;

        if (abs($delta) < 0.0001) {
            return;
        }

        if ($delta > 0) {
            $this->stockIn($branchId, [
                ['product_id' => $productId, 'qty' => $delta],
            ], 'adjustment', null, 'ADJ-' . now()->format('YmdHis'), $userId);

            return;
        }

        $this->stockOut(
            branchId: $branchId,
            productId: $productId,
            qty: abs($delta),
            type: 'adjustment',
            referenceNumber: 'ADJ-' . now()->format('YmdHis'),
            userId: $userId,
            reason: $reason,
        );
    }

    /**
     * @param  Collection<int, object>|array<int, object|array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function enrichProductsWithStock(Collection|array $products, int $branchId): array
    {
        $list = $products instanceof Collection ? $products->all() : $products;
        $productIds = collect($list)->pluck('id')->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $stockMap = $this->stockTotalsForProducts($branchId, $productIds->all());
        $expiryMap = $this->earliestExpiryForProducts($branchId, $productIds->all());
        $nearExpiryMap = $this->nearExpiryFlagsForProducts($branchId, $productIds->all());

        return collect($list)->map(function ($product) use ($stockMap, $expiryMap, $nearExpiryMap) {
            if ($product instanceof Product) {
                $row = [
                    'id'          => $product->id,
                    'name'        => $product->name,
                    'sku'         => $product->sku,
                    'barcode'     => $product->barcode,
                    'price'       => $product->price,
                    'category_id' => $product->category_id,
                    'category'    => $product->relationLoaded('category') ? $product->category?->name : null,
                    'updated_at'  => $product->updated_at?->toIso8601String(),
                ];
            } else {
                $row = is_array($product) ? $product : (array) $product;
            }
            $id = (int) ($row['id'] ?? 0);
            $stock = (float) ($stockMap[$id] ?? 0);

            $row['stock'] = $stock;
            $row['earliest_expiry'] = $expiryMap[$id] ?? null;
            $row['near_expiry'] = (bool) ($nearExpiryMap[$id] ?? false);
            $row['low_stock'] = $stock > 0 && $stock <= self::LOW_STOCK_THRESHOLD;
            $row['out_of_stock'] = $stock <= 0;

            return $row;
        })->values()->all();
    }

    /**
     * @param  array<int>  $productIds
     * @return array<int, float>
     */
    public function stockTotalsForProducts(int $branchId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        if (! $this->batchInventoryEnabled()) {
            return StockMovement::where('branch_id', $branchId)
                ->whereIn('product_id', $productIds)
                ->selectRaw('product_id, SUM(qty) as total')
                ->groupBy('product_id')
                ->pluck('total', 'product_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        $batchTotals = InventoryBatch::forBranch($branchId)
            ->whereIn('product_id', $productIds)
            ->notExpired()
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $hasBatches = InventoryBatch::forBranch($branchId)
            ->whereIn('product_id', $productIds)
            ->distinct()
            ->pluck('product_id')
            ->all();

        $movementTotals = StockMovement::where('branch_id', $branchId)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $result = [];
        foreach ($productIds as $productId) {
            if (in_array($productId, $hasBatches, true)) {
                $result[$productId] = $batchTotals[$productId] ?? 0.0;
            } else {
                $result[$productId] = $movementTotals[$productId] ?? 0.0;
            }
        }

        return $result;
    }

    /**
     * @param  array<int>  $productIds
     * @return array<int, string>
     */
    public function earliestExpiryForProducts(int $branchId, array $productIds): array
    {
        if ($productIds === [] || ! $this->batchInventoryEnabled()) {
            return [];
        }

        return InventoryBatch::forBranch($branchId)
            ->whereIn('product_id', $productIds)
            ->withStock()
            ->notExpired()
            ->whereNotNull('expiry_date')
            ->selectRaw('product_id, MIN(expiry_date) as earliest')
            ->groupBy('product_id')
            ->pluck('earliest', 'product_id')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
    }

    /**
     * @param  array<int>  $productIds
     * @return array<int, bool>
     */
    public function nearExpiryFlagsForProducts(int $branchId, array $productIds): array
    {
        $result = [];
        foreach ($productIds as $productId) {
            $result[$productId] = false;
        }

        if ($productIds === [] || ! $this->batchInventoryEnabled()) {
            return $result;
        }

        $cutoff = now()->addDays(self::NEAR_EXPIRY_DAYS)->toDateString();

        $flags = InventoryBatch::forBranch($branchId)
            ->whereIn('product_id', $productIds)
            ->withStock()
            ->notExpired()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->distinct()
            ->pluck('product_id')
            ->flip()
            ->map(fn () => true)
            ->all();

        $result = [];
        foreach ($productIds as $productId) {
            $result[$productId] = isset($flags[$productId]);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forecastReport(int $branchId, int $lookbackDays = 30, int $reorderCoverDays = 14): array
    {
        return app(InventoryForecastService::class)->forecastReport($branchId, $lookbackDays, $reorderCoverDays);
    }

    protected function batchInventoryEnabled(): bool
    {
        return Schema::hasTable('inventory_batches');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filterBatchAttributes(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn ($value, string $column) => Schema::hasColumn('inventory_batches', $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    protected function recordMovement(
        int $branchId,
        int $productId,
        float $qty,
        string $type,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $userId = null,
        ?int $shiftId = null,
        ?int $batchId = null,
        ?string $reason = null,
    ): StockMovement {
        $attributes = [
            'branch_id'          => $branchId,
            'shift_id'           => $shiftId,
            'product_id'         => $productId,
            'user_id'            => $userId,
            'inventory_batch_id' => $batchId,
            'type'               => $type,
            'qty'                => $qty,
            'reference_id'       => $referenceId,
            'reference_number'   => $referenceNumber,
            'reason'             => $reason,
        ];

        return StockMovement::create(
            array_filter(
                $attributes,
                fn ($value, string $column) => Schema::hasColumn('stock_movements', $column),
                ARRAY_FILTER_USE_BOTH,
            ),
        );
    }
}
