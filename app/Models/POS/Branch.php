<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Branch extends Model
{
    protected $fillable = ['name', 'address'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
