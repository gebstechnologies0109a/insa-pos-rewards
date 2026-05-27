<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MayaCheckoutSession extends Model
{
    protected $table = 'epay_maya_checkout_sessions';

    protected $fillable = [
        'checkout_id',
        'reference_number',
        'amount',
        'currency',
        'status',
        'redirect_url',
        'request_payload',
        'webhook_payload',
        'retailer_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'webhook_payload' => 'array',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }
}
