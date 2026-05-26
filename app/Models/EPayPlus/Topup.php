<?php

namespace App\Models\EPayPlus;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Topup extends Model
{
    protected $table = 'epay_topups';

    protected $fillable = [
        'retailer_id', 'approved_by', 'amount', 'payment_method',
        'reference_number', 'proof_url', 'status', 'remarks',
        'balance_before', 'balance_after', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(int $userId): void
    {
        $retailer = $this->retailer;
        $this->update([
            'status' => 'APPROVED',
            'approved_by' => $userId,
            'balance_before' => $retailer->balance,
            'balance_after' => $retailer->balance + $this->amount,
            'approved_at' => now(),
        ]);
        $retailer->addBalance($this->amount);
    }

    public function reject(int $userId, string $remarks = null): void
    {
        $this->update([
            'status' => 'REJECTED',
            'approved_by' => $userId,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }
}
