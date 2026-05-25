<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'shift_id',
        'product_id',
        'type',
        'qty',
        'reference_id',
        'reference_number',
    ];
}
