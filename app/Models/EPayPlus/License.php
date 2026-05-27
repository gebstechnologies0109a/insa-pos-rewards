<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class License extends Model
{
    protected $table = 'epay_licenses';

    protected $fillable = [
        'code', 'type', 'status', 'retailer_id', 'device_id',
        'machine_uid', 'notes', 'activated_at', 'expires_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function isValid(): bool
    {
        if (!in_array($this->status, ['available', 'active'])) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, ['revoked', 'blocked', 'expired']);
    }

    public static function generateCode(string $type = 'retailer'): string
    {
        do {
            $code = 'EPAY-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function activate(Device $device, string $machineUid): void
    {
        $this->update([
            'status' => 'active',
            'device_id' => $device->id,
            'machine_uid' => $machineUid,
            'activated_at' => now(),
        ]);

        $device->update([
            'machine_uid' => $machineUid,
            'license_id' => $this->id,
            'retailer_id' => $this->retailer_id ?? $device->retailer_id,
        ]);
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function block(): void
    {
        $this->update(['status' => 'blocked']);
    }

    public function transferTo(?int $retailerId): void
    {
        $this->update([
            'retailer_id' => $retailerId,
            'status' => 'available',
            'device_id' => null,
            'machine_uid' => null,
            'activated_at' => null,
        ]);
    }
}
