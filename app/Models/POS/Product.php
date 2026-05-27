<?php

namespace App\Models\POS;

use App\Models\Inventory\InventoryBatch;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const LOW_STOCK_THRESHOLD = 10;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'price',
        'category_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price'  => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function currentStock(int $branchId): float
    {
        return app(InventoryService::class)->getStockOnHand($branchId, $this->id);
    }

    public function earliestExpiry(int $branchId): ?string
    {
        return app(InventoryService::class)->earliestExpiryDate($branchId, $this->id);
    }

    public function isLowStock(int $branchId): bool
    {
        $stock = $this->currentStock($branchId);

        return $stock > 0 && $stock <= self::LOW_STOCK_THRESHOLD;
    }

    public function hasNearExpiry(int $branchId, int $withinDays = 7): bool
    {
        return app(InventoryService::class)->hasNearExpiry($branchId, $this->id, $withinDays);
    }
}
