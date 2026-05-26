<?php

namespace App\Models\EPayPlus;

use Illuminate\Database\Eloquent\Model;

class EPaySetting extends Model
{
    protected $table = 'epay_settings';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
