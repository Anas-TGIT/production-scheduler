<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Wall-clock times typed in forms are in the display timezone; storage and calculations are in UTC.
 * Single place for that conversion.
 */
final class DisplayTime
{
    public static function timezone(): string
    {
        return (string) config('scheduling.display_timezone');
    }
    public static function toUtc(string $wallClock): CarbonImmutable
    {
        return CarbonImmutable::parse($wallClock, self::timezone())->utc();
    }
}
