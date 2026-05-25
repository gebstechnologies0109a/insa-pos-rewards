<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    protected $table = 'pos_sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'sku',
        'barcode',
        'qty',
        'price',
        'discount',
        'line_total',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }
}
