<?php

namespace App\Support;

/**
 * Closed vocabulary for when a dose is taken (after food, at night, …).
 *
 * Stored as a key so print can render both English and Bangla through
 * {@see Bilingual} — free text could not be translated. Shorthand tokens
 * (`af`, `bf`, …) are for the desktop Rx desk commit line.
 */
class PrescriptionTiming
{
    public const AFTER_FOOD = 'after_food';

    public const BEFORE_FOOD = 'before_food';

    public const EMPTY_STOMACH = 'empty_stomach';

    public const AT_NIGHT = 'at_night';

    public const WITH_FOOD = 'with_food';

    /** @var list<string> */
    public const KEYS = [
        self::AFTER_FOOD,
        self::BEFORE_FOOD,
        self::EMPTY_STOMACH,
        self::AT_NIGHT,
        self::WITH_FOOD,
    ];

    /**
     * English source strings — also the translation keys in lang/*.json.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::AFTER_FOOD => 'After food',
            self::BEFORE_FOOD => 'Before food',
            self::EMPTY_STOMACH => 'Empty stomach',
            self::AT_NIGHT => 'At night',
            self::WITH_FOOD => 'With food',
        ];
    }

    /**
     * Key => translated label, for a select in the panel's own language.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (string $label) => __($label), self::labels());
    }

    /**
     * Shorthand tokens doctors type on the commit line (`af`, `7d af`).
     *
     * @return array<string, string>
     */
    public static function shorthandMap(): array
    {
        return [
            'af' => self::AFTER_FOOD,
            'ac' => self::BEFORE_FOOD,
            'bf' => self::BEFORE_FOOD,
            'pc' => self::AFTER_FOOD,
            'es' => self::EMPTY_STOMACH,
            'hs' => self::AT_NIGHT,
            'an' => self::AT_NIGHT,
            'wf' => self::WITH_FOOD,
        ];
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed);

        if (isset(self::shorthandMap()[$lower])) {
            return self::shorthandMap()[$lower];
        }

        return in_array($trimmed, self::KEYS, true) ? $trimmed : null;
    }

    public static function bilingualLabel(?string $key): ?string
    {
        $normalized = self::normalize($key);

        if ($normalized === null) {
            return null;
        }

        $label = self::labels()[$normalized] ?? null;

        return $label === null ? null : Bilingual::label($label);
    }

    public static function displayLabel(?string $key): ?string
    {
        $normalized = self::normalize($key);

        if ($normalized === null) {
            return null;
        }

        return __(self::labels()[$normalized]);
    }
}
