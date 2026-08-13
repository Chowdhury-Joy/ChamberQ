<?php

namespace App\Support;

use Illuminate\Support\Arr;

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

    /**
     * Filament FileUpload stores a disk-relative path. Homepage blades need a
     * `/storage/…` src. Walk builder JSON and convert known media keys.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public static function promoteBuilderBlock(array $block): array
    {
        if (! isset($block['data']) || ! is_array($block['data'])) {
            return $block;
        }

        $block['data'] = self::promoteMediaFields($block['data']);

        return $block;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function promoteMediaFields(array $data): array
    {
        $keys = ['image_url', 'thumbnail_url', 'uploaded_video', 'promo_image_url'];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (is_array($value)) {
                $value = Arr::first($value);
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $public = self::toPublicPath($value);

            if ($public !== null) {
                $data[$key] = $public;
            }
        }

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $data[$key][$index] = self::promoteMediaFields($item);
                    }
                }
            } else {
                $data[$key] = self::promoteMediaFields($value);
            }
        }

        return $data;
    }
}
