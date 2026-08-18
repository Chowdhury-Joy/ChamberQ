<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Birth year is the stable identity field. Age on the pad is this calendar
 * year minus that number — it is never stored as a ticking whole-year age.
 */
final class YearOfBirth
{
    public static function maxYear(?Carbon $now = null): int
    {
        return (int) ($now ?? now())->year;
    }

    public static function minYear(?Carbon $now = null): int
    {
        return self::maxYear($now) - 120;
    }

    public static function normalize(mixed $year, ?Carbon $now = null): ?int
    {
        if ($year === null || $year === '') {
            return null;
        }

        if (! is_numeric($year)) {
            return null;
        }

        $year = (int) $year;
        $min = self::minYear($now);
        $max = self::maxYear($now);

        if ($year < $min || $year > $max) {
            return null;
        }

        return $year;
    }

    public static function fromStatedAge(?int $age, mixed $recordedAt = null, ?Carbon $now = null): ?int
    {
        if ($age === null || $age < 0 || $age > 120) {
            return null;
        }

        $at = $recordedAt instanceof Carbon
            ? $recordedAt
            : ($recordedAt ? Carbon::parse($recordedAt) : ($now ?? now()));

        return self::normalize((int) $at->year - $age, $now ?? $at);
    }

    public static function ageFromYear(?int $year, ?Carbon $now = null): ?int
    {
        $year = self::normalize($year, $now);

        if ($year === null) {
            return null;
        }

        return max(0, self::maxYear($now) - $year);
    }

    /**
     * Prefer an explicit birth year. A leftover `age` from an old client is
     * converted once, never stored as the ticking number.
     */
    public static function fromRequest(mixed $yearOfBirth, mixed $age = null, ?Carbon $now = null): ?int
    {
        $fromYear = self::normalize($yearOfBirth, $now);

        if ($fromYear !== null) {
            return $fromYear;
        }

        if ($age === null || $age === '' || ! is_numeric($age)) {
            return null;
        }

        return self::fromStatedAge((int) $age, $now, $now);
    }
}
