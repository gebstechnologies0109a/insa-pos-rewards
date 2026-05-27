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
        'email', 'address', 'balance', 'eload_balance', 'bills_balance', 'credit_limit', 'pin',
        'api_token', 'device_id', 'is_active', 'is_kiosk_enabled',
        'kiosk_pin', 'printer_address', 'printer_type', 'server_url',
        'sim_slot', 'settings', 'last_login_at',
    ];

    protected $hidden = ['pin', 'api_token', 'kiosk_pin'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'eload_balance' => 'decimal:2',
            'bills_balance' => 'decimal:2',
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

    public function deductBalance(float $amount, string $wallet = 'eload'): bool
    {
        $field = $wallet === 'bills' ? 'bills_balance' : 'eload_balance';
        if (($this->{$field} ?? $this->balance) < $amount) {
            return false;
        }
        $this->decrement($field, $amount);
        return true;
    }

    public function addBalance(float $amount, string $wallet = 'eload'): void
    {
        $field = $wallet === 'bills' ? 'bills_balance' : 'eload_balance';
        $this->increment($field, $amount);
    }

    public function hasSufficientBalance(float $amount, string $wallet = 'eload'): bool
    {
        $field = $wallet === 'bills' ? 'bills_balance' : 'eload_balance';
        return ($this->{$field} ?? $this->balance) >= $amount;
    }
}
