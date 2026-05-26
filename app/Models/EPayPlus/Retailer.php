<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retailer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'epay_retailers';

    protected $fillable = [
        'account_id', 'business_name', 'owner_name', 'mobile_number',
        'email', 'address', 'balance', 'credit_limit', 'pin',
        'api_token', 'device_id', 'is_active', 'is_kiosk_enabled',
        'kiosk_pin', 'printer_address', 'printer_type', 'server_url',
        'sim_slot', 'settings', 'last_login_at',
    ];

    protected $hidden = ['pin', 'api_token', 'kiosk_pin'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
            'is_kiosk_enabled' => 'boolean',
            'settings' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'retailer_id');
    }

    public function topups()
    {
        return $this->hasMany(Topup::class, 'retailer_id');
    }

    public function deductBalance(float $amount): bool
    {
        if ($this->balance < $amount) {
            return false;
        }
        $this->decrement('balance', $amount);
        return true;
    }

    public function addBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
