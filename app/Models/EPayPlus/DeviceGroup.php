<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceGroup extends Model
{
    protected $table = 'epay_device_groups';

    protected $fillable = [
        'name', 'description', 'location', 'color', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function devices()
    {
        return $this->hasMany(Device::class, 'group_id');
    }

    public function onlineDevices()
    {
        return $this->devices()->where('status', 'online');
    }
}
