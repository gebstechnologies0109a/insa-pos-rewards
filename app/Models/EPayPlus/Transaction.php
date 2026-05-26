<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'epay_transactions';

    protected $fillable = [
        'retailer_id', 'product_id', 'type', 'reference_number',
        'provider_code', 'product_code', 'product_name', 'target_number',
        'amount', 'fee', 'commission', 'retailer_cost', 'status',
        'payment_method', 'remarks', 'external_ref',
        'balance_before', 'balance_after', 'device_id', 'ip_address',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'commission' => 'decimal:2',
            'retailer_cost' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function markSuccess(string $externalRef = null): void
    {
        $this->update([
            'status' => 'SUCCESS',
            'external_ref' => $externalRef,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $remarks = null): void
    {
        $this->update([
            'status' => 'FAILED',
            'remarks' => $remarks,
            'completed_at' => now(),
        ]);
    }
}
