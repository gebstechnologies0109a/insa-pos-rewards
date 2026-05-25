<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;

class InventoryService
{
    public function getStockOnHand(int $branchId, int $productId): float
    {
        return (float) StockMovement::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('qty');
    }
}
