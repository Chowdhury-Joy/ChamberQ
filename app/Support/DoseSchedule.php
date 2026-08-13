<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * `1+0+1` written out for the person who has to take it.
 *
 * The plus notation is prescriber shorthand. It is on the printed pad because
 * that is what a Bangladeshi doctor and pharmacist both read at a glance, and
 * it stays there untouched. But the share link goes to the *patient's* phone,
 * and a patient reading `1+0+1` has to be taught what the three positions mean
 * before the line says anything at all.
 *
 * Deliberately narrow. Only the three-slot morning / noon / night form is
 * expanded, because that is the one whose positions have a fixed, universally
 * understood meaning here. `SOS`, a two-slot line, or anything free-typed
 * returns null and the patient sees the doctor's own text instead — a wrong
 * sentence about when to take a medicine is worse than shorthand.
 */
class DoseSchedule
{
    /** English source strings; the Bangla comes from lang/bn.json. */
    private const SLOT_LABELS = ['Morning', 'Noon', 'Night'];

    private const BANGLA_DIGITS = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    /**
     * "সকাল ১টি · রাতে ১টি", or null when the frequency is not a readable
     * three-slot pattern.
     */
    public static function sentence(?string $frequency): ?string
    {
        $slots = self::slots($frequency);

        if ($slots === null) {
            return null;
        }

        $parts = [];

        foreach ($slots as $index => $amount) {
            if ($amount === '0') {
                continue;
            }

            // Bangla only, and deliberately: the doctor's own `1+0+1` is
            // printed alongside for anyone reading the sheet in English, so
            // repeating "সকাল / Morning" three times per drug would double the
            // length of the one line the patient actually needs.
            $slot = trim((string) Lang::get(self::SLOT_LABELS[$index], [], 'bn'));

            $parts[] = $slot.' '.self::banglaNumerals($amount).'টি';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The three raw slot strings, or null if this is not a three-slot line.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private static function slots(?string $frequency): ?array
    {
        $value = trim((string) $frequency);

        if ($value === '') {
            return null;
        }

        $slots = preg_split('/\s*\+\s*/', $value) ?: [];

        if (count($slots) !== 3) {
            return null;
        }

        foreach ($slots as $slot) {
            // Every slot must be an amount. One unreadable position makes the
            // whole sentence a guess.
            if (PrescriptionQuantity::slotAmount($slot) === null) {
                return null;
            }
        }

        return [trim($slots[0]), trim($slots[1]), trim($slots[2])];
    }

    /**
     * Latin digits to Bangla. The fraction glyphs (½, ¼) are already the ones
     * used in Bangla and pass through untouched.
     */
    public static function banglaNumerals(string $value): string
    {
        return strtr($value, self::BANGLA_DIGITS);
    }
}
