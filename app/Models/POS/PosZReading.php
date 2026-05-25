<?php

namespace App\Models\POS;

use App\Models\POS\Branch;
use Illuminate\Database\Eloquent\Model;

class PosZReading extends Model
{
    protected $table = 'pos_z_readings';

    protected $fillable = [
        'branch_id',
        'terminal_id',
        'cashier_id',
        'z_count',
        'generated_at',
        'total_sales',
        'transaction_count',
        'void_total',
        'discount_total',
        'payment_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'generated_at'      => 'datetime',
            'total_sales'       => 'decimal:2',
            'void_total'        => 'decimal:2',
            'discount_total'    => 'decimal:2',
            'payment_breakdown' => 'array',
        ];
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cashier_id');
    }

    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PosSale::class, 'z_reading_id');
    }
}
