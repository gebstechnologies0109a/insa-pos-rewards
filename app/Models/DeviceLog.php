<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    protected $fillable = [
        'device_id',
        'device_model',
        'app_version',
        'android_version',
        'level',
        'tag',
        'message',
        'url',
        'extra',
        'ip',
    ];
}
