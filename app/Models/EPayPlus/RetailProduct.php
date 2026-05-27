<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailProduct extends Model
{
    protected $table = 'epay_retail_products';

    protected $fillable = [
        'retailer_id', 'name', 'description', 'sku', 'price', 'stock',
        'category', 'image_path', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRetailer($query, int $retailerId)
    {
        return $query->where('retailer_id', $retailerId);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock' => (int) $this->stock,
            'category' => $this->category,
            'imageUrl' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
        ];
    }
}
