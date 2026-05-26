<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'epay_announcements';

    protected $fillable = [
        'title', 'content', 'type', 'is_active', 'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
