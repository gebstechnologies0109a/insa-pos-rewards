<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceConfig extends Model
{
    protected $table = 'epay_device_configs';

    protected $fillable = [
        'name', 'description', 'settings', 'is_default', 'device_count',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_default' => 'boolean',
    ];

    public function devices()
    {
        return $this->hasMany(Device::class, 'config_profile_id');
    }
}
