<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'route_name',
        'method',
        'subject_id',
        'subject_type',
        'ip_address',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        ?int $userId,
        string $module,
        string $action,
        ?string $routeName = null,
        ?string $method = null,
        ?int $subjectId = null,
        ?string $subjectType = null,
        ?array $meta = null,
    ): self {
        return static::create([
            'user_id'      => $userId,
            'module'       => $module,
            'action'       => $action,
            'route_name'   => $routeName,
            'method'       => $method,
            'subject_id'   => $subjectId,
            'subject_type' => $subjectType,
            'ip_address'   => request()->ip(),
            'meta'         => $meta,
            'created_at'   => now(),
        ]);
    }
}
