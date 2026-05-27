<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class ProductPricing extends Model
{
    protected $table = 'epay_product_pricing';

    protected $fillable = [
        'product_id', 'product_code', 'retailer_id',
        'discount_type', 'discount_value', 'custom_price', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:4',
        'custom_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function applyToPrice(float $basePrice): float
    {
        return match ($this->discount_type) {
            'override' => (float) ($this->custom_price ?? $basePrice),
            'fixed' => max(0, $basePrice - (float) $this->discount_value),
            default => max(0, $basePrice * (1 - ((float) $this->discount_value / 100))),
        };
    }
}
