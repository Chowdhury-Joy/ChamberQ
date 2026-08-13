<?php

namespace App\Support;

/**
 * Hero (and similar) photos live on Laravel's public disk and are shown as
 * same-origin `/storage/…` paths so tenant sites do not depend on APP_URL.
 */
final class PublicStoredImage
{
    public static function toPublicPath(?string $stored): ?string
    {
        $stored = trim((string) $stored);

        if ($stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        if (str_starts_with($stored, '/')) {
            return $stored;
        }

        return '/storage/'.$stored;
    }

    public static function toDiskPath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return null;
        }

        $prefix = '/storage/';

        if (str_starts_with($value, $prefix)) {
            return substr($value, strlen($prefix));
        }

        return $value;
    }
}
