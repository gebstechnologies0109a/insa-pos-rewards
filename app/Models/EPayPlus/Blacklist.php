<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $table = 'epay_blacklists';

    protected $fillable = [
        'type', 'value', 'reason', 'is_active', 'blocked_by', 'blocked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'blocked_at' => 'datetime',
    ];

    public static function isBlocked(string $type, string $value): bool
    {
        return self::where('type', $type)
            ->where('value', $value)
            ->where('is_active', true)
            ->exists();
    }

    public static function checkTransaction(string $phone, ?string $accountId = null, ?string $machineUid = null): ?string
    {
        if (self::isBlocked('phone', $phone)) {
            return 'This phone number is blocked.';
        }
        if ($accountId && self::isBlocked('account', $accountId)) {
            return 'This account is blocked.';
        }
        if ($machineUid && self::isBlocked('machine', $machineUid)) {
            return 'This machine is blocked.';
        }
        return null;
    }
}
