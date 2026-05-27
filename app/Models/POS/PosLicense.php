<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosLicense extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'branch_id',
        'pos_slots',
        'active',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'active'    => 'boolean',
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getActiveFromAttribute()
    {
        return $this->starts_at;
    }

    public function setActiveFromAttribute($value): void
    {
        $this->attributes['starts_at'] = $value;
    }

    public function getActiveToAttribute()
    {
        return $this->ends_at;
    }

    public function setActiveToAttribute($value): void
    {
        $this->attributes['ends_at'] = $value;
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status === self::STATUS_SUSPENDED || $this->status === self::STATUS_EXPIRED) {
            return false;
        }

        if (! $this->active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
