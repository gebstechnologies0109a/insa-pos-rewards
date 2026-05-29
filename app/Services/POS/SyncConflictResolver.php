<?php

namespace App\Services\POS;

use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;

class SyncConflictResolver
{
    public const TYPE_PRICE_MISMATCH = 'price_mismatch';

    public const TYPE_INVENTORY_MISMATCH = 'inventory_mismatch';

    public const TYPE_EXPIRY_MISMATCH = 'expiry_mismatch';

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function detectSaleItemConflicts(int $branchId, array $items): array
    {
        $conflicts = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId < 1) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $localPrice = (float) ($item['price'] ?? 0);
            if (abs((float) $product->price - $localPrice) > 0.01) {
                $conflicts[] = $this->conflict(
                    self::TYPE_PRICE_MISMATCH,
                    $productId,
                    (string) ($item['product_name'] ?? $product->name),
                    'price',
                    $localPrice,
                    (float) $product->price,
                );
            }

            $qty = (float) ($item['qty'] ?? 0);
            $stock = $this->inventory->getStockOnHand($branchId, $productId);
            if ($stock < $qty) {
                $conflicts[] = $this->conflict(
                    self::TYPE_INVENTORY_MISMATCH,
                    $productId,
                    (string) ($item['product_name'] ?? $product->name),
                    'stock',
                    $qty,
                    $stock,
                );
            }

            $earliest = $this->inventory->earliestExpiryDate($branchId, $productId);
            $localExpiry = $item['earliest_expiry'] ?? $item['expiry_date'] ?? null;
            if ($earliest && $localExpiry) {
                $serverDate = Carbon::parse($earliest)->toDateString();
                $localDate = Carbon::parse($localExpiry)->toDateString();
                if ($serverDate !== $localDate) {
                    $conflicts[] = $this->conflict(
                        self::TYPE_EXPIRY_MISMATCH,
                        $productId,
                        (string) ($item['product_name'] ?? $product->name),
                        'earliest_expiry',
                        $localDate,
                        $serverDate,
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function conflict(
        string $type,
        int $productId,
        string $productName,
        string $field,
        mixed $localValue,
        mixed $serverValue,
    ): array {
        return [
            'type'          => $type,
            'product_id'    => $productId,
            'product_name'  => $productName,
            'field'         => $field,
            'local_value'   => $localValue,
            'server_value'  => $serverValue,
        ];
    }
}
