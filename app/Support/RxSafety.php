<?php

namespace App\Support;

use App\Models\DrugInteraction;

/**
 * Non-blocking prescription safety checks for the Rx pad.
 *
 * These warn only — a doctor may have a good reason to override. Nothing here
 * blocks save.
 */
class RxSafety
{
    /**
     * Shown wherever a warning is, and deliberately never omitted.
     *
     * This is what stands in place of a signature. The pair list is compiled
     * from general pharmacology rather than a licensed clinical database, no
     * clinician is named against it (owner decision, 2026-08-12), and it is
     * knowingly incomplete — 221 pairs, and 3.7% of the catalogue is drugs
     * absent from every drug vocabulary we could check against. A doctor is
     * entitled to know that before deciding what a silent screen means.
     *
     * Kept as one constant so a display that shows warnings cannot quietly
     * ship without it.
     */
    public const DISCLAIMER = 'Reference only, and not a complete list — use your own judgement.';

    /**
     * Find duplicate generics or brands across prescription rows.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string> Human-readable warning lines
     */
    public static function duplicateWarnings(array $items): array
    {
        $warnings = [];
        $generics = [];
        $brands = [];

        foreach ($items as $item) {
            $brand = self::normalizeToken($item['medicine_name'] ?? null);
            $generic = self::normalizeToken($item['generic_name'] ?? null);

            if ($brand !== null) {
                $brands[$brand][] = $item['medicine_name'] ?? $brand;
            }

            if ($generic !== null) {
                $generics[$generic][] = $item['medicine_name'] ?? $generic;
            }
        }

        foreach ($generics as $generic => $names) {
            if (count($names) < 2) {
                continue;
            }

            $unique = array_values(array_unique(array_map('strval', $names)));

            if (count($unique) < 2) {
                continue;
            }

            $warnings[] = __('Same generic on multiple lines: :generic (:brands)', [
                'generic' => $generic,
                'brands' => implode(', ', $unique),
            ]);
        }

        foreach ($brands as $brand => $names) {
            if (count($names) < 2) {
                continue;
            }

            $warnings[] = __('Duplicate brand: :brand', [
                'brand' => $names[0],
            ]);
        }

        return $warnings;
    }

    /**
     * Match free-text allergy notes against prescribed brand/generic names.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function allergyWarnings(?string $allergies, array $items): array
    {
        if (blank($allergies)) {
            return [];
        }

        $tokens = self::allergyTokens($allergies);

        if ($tokens === []) {
            return [];
        }

        $warnings = [];

        foreach ($items as $item) {
            $brand = (string) ($item['medicine_name'] ?? '');
            $generic = (string) ($item['generic_name'] ?? '');

            if ($brand === '' && $generic === '') {
                continue;
            }

            $haystack = mb_strtolower(trim($brand.' '.$generic));

            foreach ($tokens as $token) {
                if (self::tokenMatches($token, $haystack)) {
                    $label = filled($brand) ? $brand : $generic;
                    $warnings[] = __('Allergy note may match: :allergy ↔ :medicine', [
                        'allergy' => $token,
                        'medicine' => $label,
                    ]);

                    break;
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Pairs on this prescription that should not normally go together.
     *
     * Matches on **ingredients**, not brands: `DrugIngredients::split()` breaks
     * a combination product into its parts and the salt-stripped spelling is
     * tried too, so `Diclofenac Sodium` still meets `warfarin`.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function interactionWarnings(array $items): array
    {
        // ingredient => the medicine name the doctor will recognise it by
        $byIngredient = [];

        foreach ($items as $item) {
            $label = trim((string) ($item['medicine_name'] ?? '')) ?: trim((string) ($item['generic_name'] ?? ''));

            foreach (DrugIngredients::split($item['generic_name'] ?? null) as $ingredient) {
                foreach (DrugIngredients::variants($ingredient) as $variant) {
                    $byIngredient[$variant] ??= $label !== '' ? $label : $variant;
                }
            }
        }

        if (count($byIngredient) < 2) {
            return [];
        }

        $names = array_keys($byIngredient);

        $pairs = DrugInteraction::query()
            ->whereIn('ingredient_a', $names)
            ->whereIn('ingredient_b', $names)
            ->get();

        $warnings = [];

        foreach ($pairs as $pair) {
            $a = $byIngredient[$pair->ingredient_a] ?? $pair->ingredient_a;
            $b = $byIngredient[$pair->ingredient_b] ?? $pair->ingredient_b;

            // Both ingredients can resolve to the same prescription line for a
            // combination product; that is not a clash between two medicines.
            if (mb_strtolower($a) === mb_strtolower($b)) {
                continue;
            }

            $warnings[] = $pair->severity === DrugInteraction::SEVERITY_AVOID
                ? __('Avoid together — :a + :b: :effect. :action', [
                    'a' => $a, 'b' => $b, 'effect' => $pair->effect, 'action' => (string) $pair->action,
                ])
                : __('Check — :a + :b: :effect. :action', [
                    'a' => $a, 'b' => $b, 'effect' => $pair->effect, 'action' => (string) $pair->action,
                ]);
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Lines this checker could **not** verify, named so the doctor knows.
     *
     * The whole feature turns on this. A medicine with no usable generic name —
     * a brand typed by hand, or one of the 3.7% of the catalogue with no entry
     * in any drug vocabulary — produces no interaction warning, and a doctor
     * who sees no warning reasonably concludes there is nothing to worry about.
     * Silence would convert "we did not check" into "it is fine". So the lines
     * that could not be checked are reported as plainly as the clashes that
     * could.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function uncheckedMedicines(array $items): array
    {
        $unchecked = [];

        foreach ($items as $item) {
            $label = trim((string) ($item['medicine_name'] ?? ''));

            if ($label === '') {
                continue;
            }

            if (DrugIngredients::split($item['generic_name'] ?? null) === []) {
                $unchecked[$label] = true;
            }
        }

        return array_keys($unchecked);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function allWarnings(?string $allergies, array $items): array
    {
        $unchecked = self::uncheckedMedicines($items);

        return array_values(array_unique(array_merge(
            self::duplicateWarnings($items),
            self::allergyWarnings($allergies, $items),
            self::interactionWarnings($items),
            $unchecked === [] ? [] : [
                __('Not checked for clashes (no generic name): :medicines', [
                    'medicines' => implode(', ', $unchecked),
                ]),
            ],
        )));
    }

    /**
     * @return list<string>
     */
    private static function allergyTokens(string $allergies): array
    {
        $parts = preg_split('/[,;\n\/]+/', $allergies) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): ?string => self::normalizeToken($part),
            $parts,
        )));
    }

    private static function normalizeToken(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtolower($trimmed);
    }

    private static function tokenMatches(string $token, string $haystack): bool
    {
        if (strlen($token) < 3) {
            return false;
        }

        if (str_contains($haystack, $token)) {
            return true;
        }

        // Whole-word match for short allergy names inside longer strings.
        return (bool) preg_match('/\b'.preg_quote($token, '/').'\b/u', $haystack);
    }
}
