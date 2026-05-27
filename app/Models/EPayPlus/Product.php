<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'epay_products';

    protected $fillable = [
        'provider_id', 'type', 'billing_type', 'code', 'name', 'amount',
        'retailer_price', 'fee', 'commission', 'description',
        'keyword', 'sms_format', 'is_active', 'sort_order', 'validity_days',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'retailer_price' => 'decimal:2',
            'fee' => 'decimal:2',
            'commission' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePrepaid($query)
    {
        return $query->where('billing_type', 'prepaid');
    }

    public function scopePostpaid($query)
    {
        return $query->where('billing_type', 'postpaid');
    }
}
