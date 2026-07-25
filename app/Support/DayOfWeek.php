<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Single source of truth for the day-of-week value used across scheduling.
 *
 * Both `schedule_sessions.day_of_week` and `lab_collection_slots.day_of_week`
 * store Carbon's `dayOfWeek`: 0 (Sunday) through 6 (Saturday). Labels are
 * resolved through Carbon so they follow the active application locale.
 */
class DayOfWeek
{
    public const SUNDAY = 0;

    public const SATURDAY = 6;

    /**
     * Day labels keyed by the stored integer, in week order.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $sunday = CarbonImmutable::now()->startOfWeek(self::SUNDAY);

        $options = [];

        for ($day = self::SUNDAY; $day <= self::SATURDAY; $day++) {
            $options[$day] = $sunday->addDays($day)->translatedFormat('l');
        }

        return $options;
    }

    public static function label(?int $day): string
    {
        return self::options()[$day] ?? '';
    }
}
