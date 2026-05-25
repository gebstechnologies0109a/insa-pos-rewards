<?php

namespace App\Models\Rewards;

use Illuminate\Database\Eloquent\Model;

class WalletLedger extends Model
{
    protected $fillable = [
        'member_id',
        'amount',
        'source',
        'reference',
    ];
}
