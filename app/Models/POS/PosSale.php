<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    protected $table = 'pos_sales';

    protected $fillable = [
        'local_id',
        'sale_number',
        'branch_id',
        'shift_id',
        'z_reading_id',
        'cashier_id',
        'member_id',
        'subtotal',
        'discount_total',
        'total',
        'payment_method',
        'amount_tendered',
        'change_due',
        'status',
        'is_rebated',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'sold_at'    => 'datetime',
            'is_rebated' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'sale_id');
    }

    public function shift(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function zReading(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PosZReading::class, 'z_reading_id');
    }
}
