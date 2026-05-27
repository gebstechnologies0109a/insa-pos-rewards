<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class OtaUpdate extends Model
{
    protected $table = 'epay_ota_updates';

    protected $fillable = [
        'version', 'filename', 'file_path', 'file_size', 'checksum',
        'release_notes', 'rollout_type', 'rollout_percentage',
        'target_group_id', 'status', 'success_count', 'failure_count',
        'pending_count', 'released_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'rollout_percentage' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'pending_count' => 'integer',
        'released_at' => 'datetime',
    ];

    public function targetGroup()
    {
        return $this->belongsTo(DeviceGroup::class, 'target_group_id');
    }

    public function deviceStatuses()
    {
        return $this->hasMany(DeviceUpdateStatus::class, 'ota_update_id');
    }

    public function getProgressPercentageAttribute(): int
    {
        $total = $this->success_count + $this->failure_count + $this->pending_count;
        if ($total === 0) return 0;
        return (int) round(($this->success_count / $total) * 100);
    }
}
