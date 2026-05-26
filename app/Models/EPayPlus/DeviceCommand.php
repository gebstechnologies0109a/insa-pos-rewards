<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceCommand extends Model
{
    protected $table = 'epay_device_commands';

    protected $fillable = [
        'device_id', 'command', 'params', 'status', 'result',
        'sent_at', 'executed_at', 'expires_at',
    ];

    protected $casts = [
        'params' => 'array',
        'sent_at' => 'datetime',
        'executed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
