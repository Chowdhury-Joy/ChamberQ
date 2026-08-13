<?php

namespace App\Support;

/**
 * Common investigations for the Inv "+ Add" picker on the Rx desk.
 *
 * Stored as a plain comma-separated `tests_advised` string — same column the
 * phone modal and print already use — so this is a UI list, not a new table.
 */
class InvestigationChips
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'ECG',
            'Echo',
            'Troponin I',
            'CBC',
            'RBS',
            'FBS',
            'HbA1c',
            'Lipid profile',
            'S. Creatinine',
            'Urine R/E',
            'X-ray Chest P/A',
            'USG of whole abdomen',
            'TSH',
            'LFT',
            'S. Electrolytes',
        ];
    }

    /**
     * @return list<string>
     */
    public static function parse(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s*[,;\n]+\s*/u', $text) ?: [];

        $items = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '' || in_array($part, $items, true)) {
                continue;
            }

            $items[] = $part;
        }

        return $items;
    }

    /**
     * @param  list<string>  $items
     */
    public static function format(array $items): string
    {
        $clean = [];

        foreach ($items as $item) {
            $item = trim((string) $item);

            if ($item === '' || in_array($item, $clean, true)) {
                continue;
            }

            $clean[] = $item;
        }

        return implode(', ', $clean);
    }
}
