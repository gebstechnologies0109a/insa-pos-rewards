<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ManilaDateRange
{
    public const DEFAULT_LIST_DAYS = 90;

    public static function timezone(): string
    {
        return config('app.timezone', 'Asia/Manila');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function todayBounds(): array
    {
        $start = self::now()->copy()->startOfDay();

        return [$start, $start->copy()->endOfDay()];
    }

    public static function thisWeekBounds(): array
    {
        $now = self::now();

        return [$now->copy()->startOfWeek(), $now->copy()->endOfDay()];
    }

    public static function thisMonthBounds(): array
    {
        $now = self::now();

        return [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
    }

    public static function lastDaysBounds(int $days): array
    {
        $now = self::now();

        return [$now->copy()->subDays(max($days, 1) - 1)->startOfDay(), $now->copy()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function fromStrings(?string $from, ?string $to): ?array
    {
        if ($from === null && $to === null) {
            return null;
        }

        $tz = self::timezone();
        $start = $from
            ? Carbon::parse($from, $tz)->startOfDay()
            : Carbon::create(1970, 1, 1, 0, 0, 0, $tz);
        $end = $to
            ? Carbon::parse($to, $tz)->endOfDay()
            : self::now()->copy()->endOfDay();

        return [$start, $end];
    }

    public static function applyBetween(Builder $query, string $column, array $bounds): Builder
    {
        return $query->whereBetween($column, $bounds);
    }
}
