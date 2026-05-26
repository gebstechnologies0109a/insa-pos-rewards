<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'epay_devices';

    protected $fillable = [
        'retailer_id', 'device_id', 'name', 'type', 'status',
        'app_version', 'os_version', 'model', 'location', 'group_zone',
        'config', 'enabled_services', 'operating_hours',
        'last_seen_at', 'registered_at',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled_services' => 'array',
        'last_seen_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function commands()
    {
        return $this->hasMany(DeviceCommand::class, 'device_id');
    }

    public function logs()
    {
        return $this->hasMany(DeviceLog::class, 'device_id');
    }

    public function collections()
    {
        return $this->hasMany(KioskCollection::class, 'device_id');
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class, 'device_id');
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeKiosks($query)
    {
        return $query->where('type', 'kiosk');
    }

    public function scopeRetailers($query)
    {
        return $query->where('type', 'retailer');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 5;
    }
}
