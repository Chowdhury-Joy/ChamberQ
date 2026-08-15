<?php

namespace App\Support;

use App\Models\ScheduleSession;
use Carbon\Carbon;

/**
 * Shared sitting-window maths — minutes per patient and published cap.
 *
 * Overflow stools (walk_in_overflow_cap) are excluded from pace: come-around
 * for serials 1–N always divides the window by slot_cap only.
 */
class ScheduleSessionPace
{
    public const TIGHT_MINUTES_WARNING = 5;

    public static function minutesPerPatient(ScheduleSession $session): ?int
    {
        if (blank($session->start_time) || blank($session->end_time)) {
            return null;
        }

        $start = Carbon::parse($session->start_time);
        $end = Carbon::parse($session->end_time);
        $cap = max(1, (int) $session->slot_cap);
        $windowMinutes = $start->diffInMinutes($end);

        if ($windowMinutes < 1) {
            return null;
        }

        return (int) round($windowMinutes / $cap);
    }

    public static function isTight(ScheduleSession $session): bool
    {
        $minutes = self::minutesPerPatient($session);

        return $minutes !== null && $minutes < self::TIGHT_MINUTES_WARNING;
    }

    public static function publishedCap(ScheduleSession $session): int
    {
        return max(0, (int) $session->slot_cap);
    }

    public static function overflowCap(ScheduleSession $session): int
    {
        return max(0, (int) ($session->walk_in_overflow_cap ?? 0));
    }

    public static function staffCap(ScheduleSession $session): int
    {
        return self::publishedCap($session) + self::overflowCap($session);
    }
}
