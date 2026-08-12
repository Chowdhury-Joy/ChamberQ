<?php

namespace App\Support;

/**
 * Bangladesh National ID (NID / smart card) helpers.
 *
 * Accepts 10-digit (smart card) or 13-digit (older) numbers. Spaces and
 * dashes are stripped. Empty input is allowed — NID is optional on booking.
 */
final class BdNid
{
    public static function normalize(?string $nid): ?string
    {
        if ($nid === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $nid) ?? '';

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $nid): bool
    {
        $normalized = self::normalize($nid);

        if ($normalized === null) {
            return true; // blank is valid (optional)
        }

        $length = strlen($normalized);

        return $length === 10 || $length === 13;
    }
}
