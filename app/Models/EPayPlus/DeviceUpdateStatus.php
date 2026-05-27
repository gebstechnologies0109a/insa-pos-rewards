<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class DeviceUpdateStatus extends Model
{
    protected $table = 'epay_device_update_status';

    protected $fillable = [
        'device_id', 'ota_update_id', 'status',
        'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function otaUpdate()
    {
        return $this->belongsTo(OtaUpdate::class, 'ota_update_id');
    }
}
