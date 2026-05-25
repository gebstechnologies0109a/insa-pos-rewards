<?php

namespace App\Models\POS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    protected $table = 'pos_shifts';

    protected $fillable = [
        'branch_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'system_sales_total',
        'cash_variance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'  => 'datetime',
            'closed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'shift_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PosShiftAudit::class, 'shift_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ShiftAuditLog::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
