<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Labels rendered in Bangla *and* English at the same time.
 *
 * Distinct from `__()`, which picks one locale and hides the other. A printed
 * prescription is read by people who do not share a language — the patient and
 * their family read Bangla, while a pharmacist, a referred-to consultant or a
 * hospital admissions desk may only be given the English. Bangladeshi
 * prescription pads solve this by printing both, and so do we.
 *
 * Only fixed labels can be paired this way. Anything typed by a human —
 * a doctor's name, qualifications, a medicine, free-text advice — is stored in
 * one language and is passed through untouched.
 */
class Bilingual
{
    /** Languages every bilingual label carries, most-preferred first. */
    private const LANGUAGES = ['en', 'bn'];

    private const SEPARATOR = ' / ';

    /**
     * "Patient / রোগী" — or just "Patient" when no Bangla string exists yet.
     *
     * The tenant's active locale leads, so a Bangla-configured practice reads
     * its own language first on its own paperwork.
     */
    public static function label(string $key): string
    {
        $rendered = [];

        foreach (self::orderedLanguages() as $language) {
            $text = trim((string) Lang::get($key, [], $language));

            // A JSON key with no entry for this locale comes back as the key
            // itself; that is the English source string, not a translation.
            if ($text === '' || in_array($text, $rendered, true)) {
                continue;
            }

            $rendered[] = $text;
        }

        return implode(self::SEPARATOR, $rendered);
    }

    /**
     * @return list<string>
     */
    private static function orderedLanguages(): array
    {
        $active = app()->getLocale();

        if (! in_array($active, self::LANGUAGES, true)) {
            return self::LANGUAGES;
        }

        return array_merge([$active], array_values(array_diff(self::LANGUAGES, [$active])));
    }
}
