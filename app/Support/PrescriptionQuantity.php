<?php

namespace App\Support;

/**
 * How many doses a medicine line adds up to — frequency × duration.
 *
 * The pharmacist counting out a strip and the patient checking they were given
 * enough are both doing this arithmetic by hand today, from two columns printed
 * at opposite ends of the row. It is the doctor's own instruction multiplied
 * out, not a clinical judgement, so nothing here decides anything: it either
 * reads both columns unambiguously or it prints nothing.
 *
 * Silence is the correct answer far more often than a guess. `SOS` has no
 * daily count, `Continue` has no end, and a free-typed "as directed" means the
 * doctor deliberately declined to be precise. Printing an invented total over
 * any of those would be worse than printing nothing, because a number on a
 * prescription is read as the doctor's.
 */
class PrescriptionQuantity
{
    /** Days assumed for the duration presets that are not counted in days. */
    private const WEEK_DAYS = 7;

    private const MONTH_DAYS = 30;

    private const YEAR_DAYS = 365;

    /**
     * Total doses for a line, or null when either column cannot be read.
     */
    public static function total(?string $frequency, ?string $duration): ?int
    {
        $perDay = self::dosesPerDay($frequency);
        $days = self::days($duration);

        if ($perDay === null || $days === null) {
            return null;
        }

        $total = (int) ceil($perDay * $days);

        return $total > 0 ? $total : null;
    }

    /**
     * `1+0+1` → 2.0, `½+0+½` → 1.0, `SOS` → null.
     *
     * Only the plus-separated pattern is accepted. It is what the pad's own
     * chips write and what Bangladeshi doctors type, and it is the only form
     * where each slot is unambiguously one administration.
     */
    public static function dosesPerDay(?string $frequency): ?float
    {
        $value = trim((string) $frequency);

        if ($value === '') {
            return null;
        }

        $slots = preg_split('/\s*\+\s*/', $value) ?: [];

        if (count($slots) < 2) {
            return null;
        }

        $total = 0.0;

        foreach ($slots as $slot) {
            $amount = self::slotAmount($slot);

            if ($amount === null) {
                return null;
            }

            $total += $amount;
        }

        return $total > 0 ? $total : null;
    }

    /**
     * `7 days` → 7, `2 weeks` → 14, `1 month` → 30, `Continue` → null.
     */
    public static function days(?string $duration): ?int
    {
        $value = mb_strtolower(trim((string) $duration));

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*(day|days|week|weeks|month|months|year|years|d|wk|w|mo|m|y)$/u', $value, $matches)) {
            return null;
        }

        $count = (float) $matches[1];

        $multiplier = match ($matches[2]) {
            'day', 'days', 'd' => 1,
            'week', 'weeks', 'wk', 'w' => self::WEEK_DAYS,
            'month', 'months', 'mo', 'm' => self::MONTH_DAYS,
            'year', 'years', 'y' => self::YEAR_DAYS,
        };

        $days = (int) ceil($count * $multiplier);

        return $days > 0 ? $days : null;
    }

    /**
     * One position of a `1+0+1` line as a number. Public because
     * {@see DoseSchedule} has to decide whether every slot is readable before
     * it dares write the line out as a sentence for the patient.
     */
    public static function slotAmount(string $slot): ?float
    {
        $slot = trim($slot);

        if ($slot === '') {
            return null;
        }

        // The pad's own half chip, plus the two ways a doctor types it.
        $halves = ['½' => 0.5, '1/2' => 0.5, '¼' => 0.25, '1/4' => 0.25, '¾' => 0.75, '3/4' => 0.75];

        if (isset($halves[$slot])) {
            return $halves[$slot];
        }

        // `1½` — a whole plus a fraction, written without a separator.
        if (preg_match('/^(\d+)(½|¼|¾)$/u', $slot, $matches)) {
            return (float) $matches[1] + $halves[$matches[2]];
        }

        return is_numeric($slot) ? (float) $slot : null;
    }
}
