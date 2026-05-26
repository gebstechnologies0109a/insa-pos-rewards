<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $table = 'epay_providers';

    protected $fillable = [
        'type', 'code', 'name', 'category', 'logo_url',
        'sms_number', 'sms_format', 'is_active', 'sort_order', 'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'provider_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
