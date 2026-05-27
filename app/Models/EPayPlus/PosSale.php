<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    protected $table = 'epay_pos_sales';

    protected $fillable = [
        'retailer_id', 'reference', 'subtotal', 'total',
        'payment_method', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosSaleLine::class, 'pos_sale_id');
    }
}
