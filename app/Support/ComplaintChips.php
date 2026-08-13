<?php

namespace App\Support;

/**
 * Tap-to-add chief complaints for the Rx pad.
 *
 * C/C is the one box nothing can pre-fill: the app is never told why the
 * patient came. Bookings capture a name, a phone and a serial, not a symptom.
 * So the saving here is not prediction, it is not typing — three taps instead
 * of a sentence.
 *
 * The pad holds C/C as **rows** (complaint + its own duration), the way
 * ZilSoft-style pads do — Fever for 3 days and Cough for 1 week are two
 * lines, not one run-on string. The database column stays a plain text field
 * so print and the phone modal need no schema change; {@see format()} and
 * {@see parse()} are the only bridge.
 *
 * The chip *label* follows the panel locale so a Bangla-reading user can find
 * it, but the *inserted text* is always the English term. Prescriptions in
 * Bangladesh are written in English, and a C/C box holding a mix of two
 * scripts is worse than either alone. This is the same reasoning as
 * {@see Bilingual}, applied in reverse: a fixed label can be translated, the
 * clinical record it produces should stay in one language.
 */
class ComplaintChips
{
    /**
     * Grouped so the pad can show one row per body system rather than a wall
     * of forty buttons. Order is roughly by how often a chamber GP sees them.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'General' => ['Fever', 'Weakness', 'Weight loss', 'Poor appetite', 'Body ache'],
            'Chest' => ['Cough', 'Cold', 'Sore throat', 'Shortness of breath', 'Chest pain', 'Wheeze'],
            'Stomach' => ['Abdominal pain', 'Acidity', 'Nausea', 'Vomiting', 'Loose motion', 'Constipation', 'Bloating'],
            'Head' => ['Headache', 'Dizziness', 'Vertigo', 'Sleep problem'],
            'Pain' => ['Back pain', 'Neck pain', 'Joint pain', 'Knee pain', 'Leg pain'],
            'Skin' => ['Itching', 'Rash', 'Allergy'],
            'Urine' => ['Burning urination', 'Frequent urination'],
            'Other' => ['Palpitation', 'Swelling', 'Blurred vision', 'Ear pain', 'Follow-up', 'Report review'],
        ];
    }

    /**
     * Durations a complaint is usually qualified by, one per row.
     *
     * @return list<string>
     */
    public static function durations(): array
    {
        return ['3 days', '1 week', '15 days', '1 month', '6 months'];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function forGroup(string $group): array
    {
        return array_map(
            fn (string $chip): array => ['value' => $chip, 'label' => __($chip)],
            self::groups()[$group] ?? [],
        );
    }

    /**
     * Turn the stored text back into rows the pad can edit.
     *
     * Accepts the current newline format, the older comma + "×" chip format,
     * and free text with no duration — so a note written on the phone modal
     * still opens as an editable row on the desk.
     *
     * @return list<array{complaint: string, duration: string}>
     */
    public static function parse(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        if (preg_match('/\R/u', $text) === 1) {
            $parts = preg_split('/\R/u', $text) ?: [];
        } else {
            $parts = preg_split('/\s*,\s*/u', $text) ?: [];
        }

        $rows = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '') {
                continue;
            }

            // "Fever — 3 days" (current) or "Fever × 3 days" (legacy chips).
            if (preg_match('/^(.+?)\s*[—–×]\s+(.+)$/u', $part, $match) === 1) {
                $rows[] = [
                    'complaint' => trim($match[1]),
                    'duration' => trim($match[2]),
                ];

                continue;
            }

            $rows[] = [
                'complaint' => $part,
                'duration' => '',
            ];
        }

        return $rows;
    }

    /**
     * One complaint per line, duration after an em dash when present.
     *
     * Print shows this as-is with `white-space: pre-line`, so each row stays
     * a row on paper the way it does on the pad.
     *
     * @param  list<array{complaint?: ?string, duration?: ?string}>  $rows
     */
    public static function format(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $complaint = trim((string) ($row['complaint'] ?? ''));

            if ($complaint === '') {
                continue;
            }

            $duration = trim((string) ($row['duration'] ?? ''));

            $lines[] = $duration !== ''
                ? "{$complaint} — {$duration}"
                : $complaint;
        }

        return implode("\n", $lines);
    }
}
