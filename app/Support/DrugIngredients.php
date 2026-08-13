<?php

namespace App\Support;

/**
 * What ingredients is this medicine made of?
 *
 * One definition, shared by the runtime interaction check (`RxSafety`) and the
 * feasibility measurement (`drugs:coverage-report`). They must not drift: the
 * measurement's headline — 92.9% of the catalogue checkable — only describes
 * the shipped checker for as long as both split names the same way.
 *
 * Catalogue generic names are free text. Around a quarter are combinations
 * (`Amoxicillin + Clavulanic Acid`), most carry a salt or hydrate word, and a
 * few are not drugs at all (`15 vitamins and minerals`, `blood glucose
 * monitoring device`).
 */
class DrugIngredients
{
    /**
     * Trailing salt, ester and hydrate words.
     *
     * Stripping is a **fallback**, applied only when the full name does not
     * match — "Ferrous Sulfate" must not become "Ferrous".
     *
     * @var list<string>
     */
    public const SALT_WORDS = [
        'hydrochloride', 'dihydrochloride', 'hydrobromide', 'sodium', 'potassium',
        'calcium', 'magnesium', 'acetate', 'bromide', 'chloride', 'fumarate',
        'maleate', 'tartrate', 'citrate', 'phosphate', 'succinate', 'besylate',
        'besilate', 'mesylate', 'tosylate', 'valerate', 'propionate',
        'dipropionate', 'furoate', 'xinafoate', 'nitrate', 'stearate',
        'palmitate', 'gluconate', 'lactate', 'oxalate', 'trihydrate',
        'monohydrate', 'dihydrate', 'hemihydrate', 'sesquihydrate', 'anhydrous',
        'bisulphate', 'bisulfate', 'sulphate', 'sulfate', 'micronised',
        'micronized', 'usp', 'bp', 'inn', 'ph', 'axetil', 'tromethamine',
        'alfa', 'beta',
    ];

    /**
     * Free-text generic name to a list of cleaned single ingredients.
     *
     * @return list<string>
     */
    public static function split(?string $generic): array
    {
        $normalized = mb_strtolower(trim((string) $generic));

        if ($normalized === '') {
            return [];
        }

        // Bracketed marketing text: "[Pregnancy and Breast Feeding Formula]".
        $normalized = preg_replace('/[\(\[].*?[\)\]]/u', ' ', $normalized) ?? $normalized;

        $parts = preg_split('/\s*(?:\+|&|,|\/|\band\b|\bwith\b)\s*/u', $normalized) ?: [];

        $ingredients = [];

        foreach ($parts as $part) {
            $clean = self::clean($part);

            if ($clean !== null) {
                $ingredients[$clean] = true;
            }
        }

        return array_keys($ingredients);
    }

    /**
     * Every spelling an ingredient might be stored under — the name itself and
     * its salt-stripped form. Used to match a prescription line against the
     * interaction table without needing the two to agree on the salt.
     *
     * @return list<string>
     */
    public static function variants(string $ingredient): array
    {
        $variants = [$ingredient];
        $stripped = self::saltStripped($ingredient);

        if ($stripped !== null) {
            $variants[] = $stripped;
        }

        return array_values(array_unique($variants));
    }

    public static function clean(string $raw): ?string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $clean = trim($clean, " \t\n\r\0\x0B.-");

        if ($clean === '' || mb_strlen($clean) < 3) {
            return null;
        }

        // Quantity/vitamin-blend noise: "15 vitamins", "6 essential nutrients".
        if (preg_match('/^\d+\s*(vitamin|mineral|essential|nutrient)/u', $clean)) {
            return null;
        }

        return $clean;
    }

    public static function saltStripped(string $ingredient): ?string
    {
        $words = explode(' ', $ingredient);

        while (count($words) > 1 && in_array(end($words), self::SALT_WORDS, true)) {
            array_pop($words);
        }

        $stripped = implode(' ', $words);

        return $stripped !== $ingredient && mb_strlen($stripped) >= 3 ? $stripped : null;
    }
}
