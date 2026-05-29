<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use Illuminate\Support\Collection;

/**
 * FEFO batch selection and simple stock forecast — delegates stock math to InventoryService.
 */
class ForecastingService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * Pick the first FEFO batch that can satisfy qty (does not mutate stock).
     */
    public function getFefoBatch(int $branchId, int $productId, float $qty): ?InventoryBatch
    {
        if ($qty <= 0) {
            return null;
        }

        $batches = InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->withStock()
            ->notExpired()
            ->fefoOrder()
            ->get();

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ((float) $batch->quantity >= $remaining) {
                return $batch;
            }
            $remaining -= (float) $batch->quantity;
            if ($remaining <= 0) {
                return $batch;
            }
        }

        return null;
    }

    /**
     * @return array{product_id:int, branch_id:int, on_hand:float, earliest_expiry:?string, near_expiry:bool, batches:array<int, array<string, mixed>>, days_until_depleted:?int}
     */
    public function forecast(int $branchId, int $productId, float $avgDailySales = 1.0, int $horizonDays = 30): array
    {
        $onHand = $this->inventory->getStockOnHand($branchId, $productId);
        $batches = $this->fefoBatches($branchId, $productId);

        $daysUntilDepleted = null;
        if ($avgDailySales > 0 && $onHand > 0) {
            $daysUntilDepleted = (int) ceil($onHand / $avgDailySales);
        }

        return [
            'product_id'         => $productId,
            'branch_id'          => $branchId,
            'on_hand'            => $onHand,
            'earliest_expiry'    => $this->inventory->earliestExpiryDate($branchId, $productId),
            'near_expiry'        => $this->inventory->hasNearExpiry($branchId, $productId),
            'batches'            => $batches->map(fn (InventoryBatch $b) => [
                'id'           => $b->id,
                'batch_code'   => $b->batch_code,
                'expiry_date'  => $b->expiry_date?->toDateString(),
                'quantity'     => (float) $b->quantity,
            ])->values()->all(),
            'horizon_days'       => $horizonDays,
            'days_until_depleted'=> $daysUntilDepleted,
        ];
    }

    /**
     * @return Collection<int, InventoryBatch>
     */
    public function fefoBatches(int $branchId, int $productId): Collection
    {
        return InventoryBatch::forBranch($branchId)
            ->forProduct($productId)
            ->withStock()
            ->notExpired()
            ->fefoOrder()
            ->get();
    }
}
