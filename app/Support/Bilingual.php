<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * Labels rendered in Bangla *and* English at the same time.
 *
 * Distinct from `__()`, which picks one locale and hides the other. A printed
 * prescription is read by people who do not share a language — the patient and
 * their family read Bangla, while a pharmacist, a referred-to consultant or a
 * hospital admissions desk may only be given the English. Bangladeshi
 * prescription pads solve this by printing both, and so do we.
 *
 * Bangla always leads. The doctor's admin panel is English, so following the
 * active locale would put English first on the paper the patient walks out
 * with. English stays, quieter, as the second language.
 *
 * Only fixed labels can be paired this way. Anything typed by a human —
 * a doctor's name, qualifications, a medicine, free-text advice — is stored in
 * one language and is passed through untouched.
 */
class Bilingual
{
    /** Bangla first, English second — the printed pad is Bangla-focused. */
    private const LANGUAGES = ['bn', 'en'];

    private const SEPARATOR = ' / ';

    /**
     * Plain "রোগী / Patient" — useful in tests and anywhere HTML is not safe.
     */
    public static function label(string $key): string
    {
        return implode(self::SEPARATOR, self::parts($key));
    }

    /**
     * Bangla prominent, English muted — for the printed / shared pad.
     */
    public static function html(string $key): HtmlString
    {
        $parts = self::parts($key);

        if ($parts === []) {
            return new HtmlString(e($key));
        }

        if (count($parts) === 1) {
            return new HtmlString('<span class="pad-l-bn">'.e($parts[0]).'</span>');
        }

        return new HtmlString(
            '<span class="pad-l-bn">'.e($parts[0]).'</span>'
            .'<span class="pad-l-en">'.e($parts[1]).'</span>'
        );
    }

    /**
     * @return list<string>
     */
    private static function parts(string $key): array
    {
        $rendered = [];

        foreach (self::LANGUAGES as $language) {
            $text = trim((string) Lang::get($key, [], $language));

            // A JSON key with no entry for this locale comes back as the key
            // itself; that is the English source string, not a translation.
            if ($text === '' || in_array($text, $rendered, true)) {
                continue;
            }

            $rendered[] = $text;
        }

        return $rendered;
    }
}
