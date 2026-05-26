<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'epay_sms_logs';

    protected $fillable = [
        'device_id', 'direction', 'number', 'message', 'status',
        'provider', 'reference', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }
}
