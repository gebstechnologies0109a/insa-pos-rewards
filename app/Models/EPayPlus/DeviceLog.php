<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    protected $table = 'epay_device_logs';

    public $timestamps = false;

    protected $fillable = [
        'device_id', 'level', 'tag', 'message', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
