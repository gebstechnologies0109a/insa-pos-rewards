<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInItem extends Model
{
    protected $table = 'stock_in_items';

    protected $fillable = [
        'stock_in_id',
        'product_id',
        'product_name',
        'sku',
        'qty',
        'cost',
        'line_total',
    ];

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockIn::class, 'stock_in_id');
    }
}
