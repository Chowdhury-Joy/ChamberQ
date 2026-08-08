<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Keeps an SMS body inside one billable segment.
 *
 * Credits are sold to clinics as "1 credit = 1 message" (config/marketing.php:
 * 200 credits for ৳100), and `SmsService::debitOneCredit()` takes exactly one
 * per send. Networks do not bill per message, they bill per **segment**: 160
 * characters of the GSM 03.38 alphabet, but only 70 the moment a single
 * character falls outside it. One typographic dash in a 138-character
 * cancellation notice made it three segments — billed three, debited one — so
 * a clinic's balance overstated what they could actually send by roughly 3x.
 *
 * Rather than trust every caller to write GSM-safe copy (a rule that lasted
 * two days last time), `SmsService` runs every outgoing body through
 * `toSingleSegment()`. Callers may write whatever reads best — the WhatsApp
 * copy shares these strings and keeps its real dashes.
 */
class GsmText
{
    /** One segment of GSM 03.38, in 7-bit units. */
    public const SEGMENT_UNITS = 160;

    /**
     * GSM 03.38 characters that occupy two 7-bit units instead of one.
     *
     * @var list<string>
     */
    private const EXTENDED = ['^', '{', '}', '\\', '[', '~', ']', '|', '€'];

    /**
     * Printable ASCII that GSM 03.38 does not contain, mapped to a stand-in.
     *
     * @var array<string, string>
     */
    private const NOT_IN_GSM = ['`' => "'"];

    /**
     * Flatten to GSM-safe text without losing meaning.
     *
     * `Str::ascii()` does the heavy lifting: typographic dashes, curly quotes
     * and ellipses become their plain equivalents, emoji drop out, and — the
     * reason this is transliteration rather than a strip — a Bangla patient
     * name survives readably ("ফাতিমা রহমান" becomes "fatima rhman") instead
     * of vanishing from their own confirmation. URLs pass through untouched.
     */
    public static function normalize(string $text): string
    {
        $text = Str::ascii($text);
        $text = strtr($text, self::NOT_IN_GSM);

        // Str::ascii drops unmappable characters, which can leave doubled
        // spaces mid-sentence (a middle dot between two words, for example).
        $text = preg_replace('/[^\P{C}\n]+/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Billable 7-bit units, counting the extension characters as two.
     *
     * Only meaningful for GSM-encodable text — check `isGsmSafe()` first, or
     * use `segments()`, which picks the right alphabet for you.
     */
    public static function units(string $text): int
    {
        $units = mb_strlen($text);

        foreach (self::EXTENDED as $char) {
            $units += mb_substr_count($text, $char);
        }

        return $units;
    }

    /**
     * Can every character be sent in the 160-per-segment alphabet?
     *
     * One character that cannot — a typographic dash, Bangla script, an emoji
     * — drops the whole message to UCS-2 and 70 per segment. There is no
     * partial credit.
     */
    public static function isGsmSafe(string $text): bool
    {
        $length = mb_strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);

            if (! str_contains(self::gsmAlphabet(), $char) && ! in_array($char, self::EXTENDED, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How many segments the network will actually bill for this text.
     */
    public static function segments(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (self::isGsmSafe($text)) {
            $units = self::units($text);

            // Concatenated parts spend a few units on the header joining them.
            return $units <= self::SEGMENT_UNITS ? 1 : (int) ceil($units / 153);
        }

        // UCS-2: 70 per single segment, 67 once concatenated.
        $units = mb_strlen($text);

        return $units <= 70 ? 1 : (int) ceil($units / 67);
    }

    /**
     * The GSM 03.38 basic set (the extension characters are handled separately
     * because they cost two units each).
     */
    private static function gsmAlphabet(): string
    {
        static $alphabet = null;

        return $alphabet ??= '@£$¥èéùìòÇ'."\n".'Øø'."\r".'Åå'
            .'Δ_ΦΓΛΩΠΨΣΘΞ'
            .'ÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?'
            .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§'
            .'¿abcdefghijklmnopqrstuvwxyzäöñüà';
    }

    /**
     * Normalise, then guarantee the result bills as exactly one segment.
     *
     * Any trailing link is preserved whole and the prose in front of it is
     * shortened instead — a truncated ticket or prescription URL is worse than
     * a truncated sentence, because it is the only actionable part of the
     * message.
     */
    public static function toSingleSegment(string $text): string
    {
        $text = self::normalize($text);

        if (self::units($text) <= self::SEGMENT_UNITS) {
            return $text;
        }

        $url = null;

        if (preg_match('~https?://\S+~', $text, $matches)) {
            $url = $matches[0];
            $text = trim(str_replace($url, '', $text));
            $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        }

        // Some links cannot be shortened away: a signed prescription share URL
        // runs ~181 characters, longer than a whole segment on its own. One
        // segment is then physically impossible, and the choice is between a
        // bare link and a two-segment message. A naked URL from an unknown
        // number is indistinguishable from spam, so the context stays and the
        // caller is billed for what it really costs — see SmsService::send().
        if ($url !== null && self::units($url) + 1 >= self::SEGMENT_UNITS) {
            $prose = self::truncateToUnits($text, self::SEGMENT_UNITS - 3);

            return trim($prose.' '.$url);
        }

        $budget = self::SEGMENT_UNITS
            - ($url !== null ? self::units($url) + 1 : 0)
            - 3; // "..." marking the cut

        $prose = self::truncateToUnits($text, max($budget, 0));

        return trim($url !== null ? $prose.' '.$url : $prose);
    }

    /**
     * Cut to a unit budget, preferring the last word boundary.
     */
    private static function truncateToUnits(string $text, int $budget): string
    {
        if ($budget <= 0) {
            return '';
        }

        if (self::units($text) <= $budget) {
            return $text;
        }

        $cut = $text;

        while ($cut !== '' && self::units($cut) > $budget) {
            $cut = mb_substr($cut, 0, mb_strlen($cut) - 1);
        }

        $lastSpace = mb_strrpos($cut, ' ');

        // Only fall back to a word boundary if it does not gut the message.
        if ($lastSpace !== false && $lastSpace > mb_strlen($cut) / 2) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        // Plain "...", never the single-character ellipsis — that is exactly
        // the sort of character this class exists to keep out of a body.
        return rtrim($cut, " ,.;:-").'...';
    }
}
