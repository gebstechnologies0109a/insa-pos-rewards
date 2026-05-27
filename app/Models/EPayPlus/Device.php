<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'epay_devices';

    protected $fillable = [
        'retailer_id', 'device_id', 'name', 'type', 'status', 'is_locked',
        'app_version', 'current_ota_version', 'os_version', 'model', 'serial_number',
        'location', 'latitude', 'longitude', 'group_zone',
        'group_id', 'config_profile_id',
        'battery_level', 'network_type', 'signal_strength',
        'free_storage_mb', 'uptime_seconds', 'ip_address', 'mac_address',
        'config', 'enabled_services', 'operating_hours',
        'last_seen_at', 'registered_at',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled_services' => 'array',
        'last_seen_at' => 'datetime',
        'registered_at' => 'datetime',
        'is_locked' => 'boolean',
        'battery_level' => 'integer',
        'signal_strength' => 'integer',
        'free_storage_mb' => 'integer',
        'uptime_seconds' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function group()
    {
        return $this->belongsTo(DeviceGroup::class, 'group_id');
    }

    public function configProfile()
    {
        return $this->belongsTo(DeviceConfig::class, 'config_profile_id');
    }

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

    public function alerts()
    {
        return $this->hasMany(DeviceAlert::class, 'device_id');
    }

    public function activeAlerts()
    {
        return $this->alerts()->where('status', 'active');
    }

    public function updateStatuses()
    {
        return $this->hasMany(DeviceUpdateStatus::class, 'device_id');
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    public function scopeKiosks($query)
    {
        return $query->where('type', 'kiosk');
    }

    public function scopeRetailers($query)
    {
        return $query->where('type', 'retailer');
    }

    public function scopeInGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->isOnline()) return 'success';
        if ($this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 30) return 'warning';
        return 'danger';
    }

    public function getBatteryIconAttribute(): string
    {
        if ($this->battery_level === null) return 'bi-battery';
        if ($this->battery_level >= 75) return 'bi-battery-full';
        if ($this->battery_level >= 50) return 'bi-battery-half';
        if ($this->battery_level >= 25) return 'bi-battery-half';
        return 'bi-battery';
    }

    public function getSignalIconAttribute(): string
    {
        if ($this->signal_strength === null) return 'bi-wifi-off';
        if ($this->signal_strength >= -50) return 'bi-wifi';
        if ($this->signal_strength >= -70) return 'bi-wifi-2';
        return 'bi-wifi-1';
    }
}
