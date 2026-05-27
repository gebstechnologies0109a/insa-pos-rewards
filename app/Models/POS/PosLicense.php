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

    protected static function booted(): void
    {
        static::saving(function (PosLicense $license) {
            if (($license->status === null || $license->status === '') && $license->pos_slots > 0) {
                $license->status = self::STATUS_ACTIVE;
            }

            if ((bool) $license->active) {
                $license->status = self::STATUS_ACTIVE;
            } elseif ($license->status === null || $license->status === '') {
                $license->status = self::STATUS_SUSPENDED;
            }
        });
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
        if (! $this->hasActiveEntitlement()) {
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

    /**
     * License is entitled when the active flag or status column says so (kept in sync on save).
     */
    public function hasActiveEntitlement(): bool
    {
        if ((bool) $this->active) {
            return true;
        }

        return $this->normalizedStatus() === self::STATUS_ACTIVE;
    }

    public function normalizedStatus(): string
    {
        $status = $this->status;

        if ($status !== null && $status !== '') {
            return $status;
        }

        if ($this->pos_slots > 0) {
            return self::STATUS_ACTIVE;
        }

        return (bool) $this->active ? self::STATUS_ACTIVE : self::STATUS_SUSPENDED;
    }
}
