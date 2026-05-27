<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceAlert extends Model
{
    protected $table = 'epay_device_alerts';

    protected $fillable = [
        'device_id', 'type', 'severity', 'title', 'message', 'meta',
        'status', 'acknowledged_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function resolve(string $resolvedBy = 'system'): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
        ]);
    }
}
