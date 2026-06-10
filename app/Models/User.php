<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'branch_id', 'upline_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_OWNER       = 'owner';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_MANAGER     = 'manager';
    public const ROLE_CASHIER     = 'cashier';
    public const ROLE_STOCKMAN    = 'stockman';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_CASHIER,
        self::ROLE_STOCKMAN,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function upline(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'upline_id');
    }

    public function downlines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'upline_id');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\POS\Branch::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Platform / store owners who manage licenses, branches, and devices. */
    public function canAccessSuperAdminPanel(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_OWNER);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function canModifyOwnerUsers(): bool
    {
        return $this->hasRole(self::ROLE_OWNER, self::ROLE_SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_OWNER, self::ROLE_SUPER_ADMIN]);
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function isStockman(): bool
    {
        return $this->role === self::ROLE_STOCKMAN;
    }

    public function canAccessBackoffice(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER);
    }

    /**
     * Whether shift / reading revenue totals may be shown on the cashier POS UI.
     */
    public function canViewShiftTotals(): bool
    {
        return $this->hasRole(
            self::ROLE_SUPER_ADMIN,
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
        );
    }

    public function canManageUsers(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_OWNER, self::ROLE_ADMIN);
    }

    public function canManageSettings(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_OWNER, self::ROLE_ADMIN);
    }

    /**
     * Whether this user is branch-scoped (cannot see other branches).
     */
    public function isBranchScoped(): bool
    {
        return $this->hasRole(self::ROLE_CASHIER, self::ROLE_STOCKMAN);
    }

    public function hasPermission(string $ability): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $map = config('permissions', []);
        $roleCaps = $map[$this->role] ?? [];

        if (in_array('*', $roleCaps, true)) {
            return true;
        }

        return in_array($ability, $roleCaps, true);
    }
}
