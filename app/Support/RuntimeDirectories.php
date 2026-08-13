<?php

namespace App\Support;

/**
 * Directories Laravel, Livewire, and website media must be able to write.
 *
 * PHP 8.4+ turns `tempnam()` fallback into a warning. Laravel's debug handler
 * promotes that to a crash (`tempnam(): file created in the system's temporary
 * directory` in AliasLoader). Missing Livewire tmp also breaks FileUpload.
 */
final class RuntimeDirectories
{
    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return [
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app/private/livewire-tmp'),
            storage_path('app/public'),
            storage_path('app/public/webpage-hero'),
            storage_path('app/public/webpage-videos'),
            storage_path('app/public/webpage-video-thumbs'),
        ];
    }

    public static function ensure(): void
    {
        foreach (self::paths() as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            if (! is_writable($dir)) {
                @chmod($dir, 0775);
            }
        }
    }
}
