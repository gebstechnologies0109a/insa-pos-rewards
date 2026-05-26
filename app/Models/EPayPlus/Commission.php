<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'epay_commissions';

    protected $fillable = [
        'retailer_id', 'provider_code', 'product_code',
        'rate', 'type', 'tier', 'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRetailer($query, $retailerId)
    {
        return $query->where('retailer_id', $retailerId);
    }
}
