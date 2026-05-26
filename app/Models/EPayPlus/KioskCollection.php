<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class KioskCollection extends Model
{
    protected $table = 'epay_kiosk_collections';

    protected $fillable = [
        'device_id', 'amount', 'coins_amount', 'bills_amount',
        'transaction_count', 'collected_by', 'notes',
        'period_start', 'period_end', 'collected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'coins_amount' => 'decimal:2',
        'bills_amount' => 'decimal:2',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
