<?php

namespace App\Models\EPayPlus;

use App\Enums\MayaBillerState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MayaBillerTransaction extends Model
{
    protected $table = 'epay_maya_biller_transactions';

    protected $fillable = [
        'request_reference_no',
        'maya_transaction_id',
        'state',
        'biller_code',
        'account_number',
        'amount',
        'fee',
        'currency',
        'customer_name',
        'customer_phone',
        'raw_validate_payload',
        'raw_post_payload',
        'callback_sent_at',
        'callback_response',
        'epay_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'state' => MayaBillerState::class,
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'raw_validate_payload' => 'array',
            'raw_post_payload' => 'array',
            'callback_response' => 'array',
            'callback_sent_at' => 'datetime',
        ];
    }

    public function epayTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'epay_transaction_id');
    }
}
