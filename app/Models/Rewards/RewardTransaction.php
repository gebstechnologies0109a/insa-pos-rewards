<?php

namespace App\Models\Rewards;

use Illuminate\Database\Eloquent\Model;

class RewardTransaction extends Model
{
    protected $fillable = [
        'member_id',
        'sale_id',
        'type',
        'amount',
    ];
}
