<?php

namespace App\Models\Inventory;

use App\Models\POS\Branch;
use App\Models\POS\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'batch_code',
        'expiry_date',
        'quantity',
        'cost_price',
        'supplier_name',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'  => 'date',
            'quantity'     => 'decimal:3',
            'cost_price'   => 'decimal:2',
            'received_at'  => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function expiryAlerts(): HasMany
    {
        return $this->hasMany(ExpiryAlert::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->toDateString());
        });
    }

    public function scopeFefoOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->lt(now()->startOfDay());
    }

    public function isNearExpiry(int $withinDays = 7): bool
    {
        if ($this->expiry_date === null || $this->isExpired()) {
            return false;
        }

        return $this->expiry_date->lte(now()->addDays($withinDays)->startOfDay());
    }
}
