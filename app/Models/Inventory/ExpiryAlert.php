<?php

namespace App\Models\Inventory;

use App\Models\POS\Branch;
use App\Models\POS\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpiryAlert extends Model
{
    public const TYPE_THIRTY_DAY = 'thirty_day';

    public const TYPE_SEVEN_DAY = 'seven_day';

    public const TYPE_EXPIRED = 'expired';

    protected $fillable = [
        'inventory_batch_id',
        'branch_id',
        'product_id',
        'alert_type',
        'expiry_date',
        'quantity',
        'handled_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'    => 'date',
            'quantity'       => 'decimal:3',
            'handled_at'     => 'datetime',
            'snoozed_until'  => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('handled_at')
            ->where(function (Builder $q) {
                $q->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            });
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
