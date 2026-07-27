<?php

namespace App\Support;

/**
 * Allowlist URL schemes for tenant-authored hrefs (page-builder CTAs, maps, etc.).
 *
 * Blade {{ }} escapes HTML special characters but leaves javascript: URLs intact,
 * so scheme checking must happen separately from HTML escaping.
 */
class SafeUrl
{
    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Return a safe href, or $fallback when the URL uses a disallowed scheme.
     */
    public static function href(?string $url, string $fallback = '#'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        // Same-origin relative paths and in-page fragments.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            // Protocol-relative URLs (//evil.example) are not same-origin paths.
            if (str_starts_with($url, '//')) {
                return $fallback;
            }

            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            return $fallback;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return $fallback;
        }

        return $url;
    }

    /**
     * Scrub known URL keys on a page-builder block (top-level and nested repeaters).
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public static function sanitizeBuilderBlock(array $block): array
    {
        if (! isset($block['data']) || ! is_array($block['data'])) {
            return $block;
        }

        $block['data'] = self::sanitizeUrlFields($block['data']);

        return $block;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitizeUrlFields(array $data): array
    {
        $urlKeys = [
            'cta_link',
            'secondary_cta_link',
            'google_maps_url',
            'video_url',
            'thumbnail_url',
            'link_url',
            'link',
            'image_url',
        ];

        foreach ($urlKeys as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $original = trim($data[$key]);
                if ($original === '') {
                    continue;
                }
                $safe = self::href($original, '');
                $data[$key] = $safe;
            }
        }

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            // Nested repeater rows (videos, items, articles, gallery, …)
            $isList = array_is_list($value);
            if ($isList) {
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $data[$key][$index] = self::sanitizeUrlFields($item);
                    }
                }
            } else {
                $data[$key] = self::sanitizeUrlFields($value);
            }
        }

        return $data;
    }
}
