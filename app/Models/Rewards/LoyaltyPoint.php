<?php

namespace App\Models\Rewards;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPoint extends Model
{
    protected $fillable = [
        'member_id',
        'points',
        'reference',
    ];
}
