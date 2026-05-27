<?php

namespace App\Support;

class PhilippineMobile
{
    public static function normalize(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }

    public static function isValid(string $mobile): bool
    {
        $digits = self::normalize($mobile);

        return (bool) preg_match('/^09\d{9}$/', $digits);
    }
}
