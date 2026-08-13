<?php

namespace App\Support;

/**
 * Why-this-medicine suggestions for the Rx desk Reason box.
 *
 * A curated list, not the catalogue's `indications` column — that column is a
 * drug-class hint and sometimes marketing prose, and putting it on a signed
 * pad is the same class of error as carrying last visit's weight forward.
 *
 * Also not ranked by what this doctor has diagnosed: the app does not learn
 * from consultations (2026-08-11). The condition search API is a second
 * source the desk may merge in once three characters are typed.
 */
class IndicationSuggestions
{
    /**
     * Common reasons a chamber GP writes under a brand. English is stored,
     * same rule as C/C chips: the clinical record stays in one language.
     *
     * @return list<string>
     */
    public static function common(): array
    {
        return [
            'Fever',
            'Pain',
            'Headache',
            'Cough',
            'Cold',
            'Acidity',
            'Nausea',
            'Vomiting',
            'Allergy',
            'Asthma',
            'Infection',
            'Hypertension',
            'Diabetes',
            'Weakness',
            'Body ache',
            'Abdominal pain',
            'Diarrhoea',
            'Insomnia',
        ];
    }

    /**
     * @return list<array{name: string}>
     */
    public static function matching(string $query, int $limit = 8): array
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return [];
        }

        $scored = [];

        foreach (self::common() as $name) {
            $term = mb_strtolower($name);

            if ($term === $needle) {
                $score = 100;
            } elseif (str_starts_with($term, $needle)) {
                $score = 80;
            } elseif (str_contains($term, $needle)) {
                $score = 60;
            } else {
                continue;
            }

            $scored[] = ['name' => $name, 'score' => $score];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['name'], $b['name']));

        return array_map(
            fn (array $row): array => ['name' => $row['name']],
            array_slice($scored, 0, $limit)
        );
    }
}
