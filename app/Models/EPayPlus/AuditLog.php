<?php

namespace App\Models\EPayPlus;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'epay_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'description', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?int $userId, string $action, $model = null, string $description = ''): self
    {
        return static::create([
            'user_id'     => $userId,
            'action'      => $action,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->id ?? null,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
    }
}
