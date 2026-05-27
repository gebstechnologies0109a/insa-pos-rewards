<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;

class Branch extends Model
{
    protected $fillable = ['name', 'address'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function license(): HasOne
    {
        return $this->hasOne(PosLicense::class);
    }

    public function terminalSessions(): HasMany
    {
        return $this->hasMany(PosTerminalSession::class);
    }

    public function activeTerminalSessions(): HasMany
    {
        return $this->hasMany(PosTerminalSession::class)->where('is_active', true);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }

    public function openShifts(): HasMany
    {
        return $this->hasMany(PosShift::class)->where('status', 'open');
    }

    public function getPosSlots(): int
    {
        $license = $this->license;

        if (! $license || ! $license->isCurrentlyActive()) {
            return 1;
        }

        return $license->pos_slots;
    }
}
