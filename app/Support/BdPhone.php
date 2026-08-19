<?php

namespace App\Support;

/**
 * Bangladesh mobile numbers as patients type them: 01XXXXXXXXX everywhere visible.
 * Gateway conversion to 880… happens only at SMS/WhatsApp send time.
 */
final class BdPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '88')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);

        return (bool) preg_match('/^01[3-9]\d{8}$/', $normalized);
    }

    /**
     * @return list<string>
     */
    public static function lookupVariants(string $phone): array
    {
        $digits = self::normalize($phone);

        return array_values(array_unique([
            $digits,
            '88'.$digits,
            '+88'.$digits,
            trim($phone),
        ]));
    }

    public static function maskForDisplay(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (strlen($normalized) < 6) {
            return $normalized;
        }

        return substr($normalized, 0, 3).'****'.substr($normalized, -4);
    }
}
