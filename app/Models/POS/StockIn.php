<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIn extends Model
{
    protected $table = 'stock_ins';

    protected $fillable = [
        'stock_in_number',
        'branch_id',
        'user_id',
        'supplier_name',
        'total_cost',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockInItem::class, 'stock_in_id');
    }
}
