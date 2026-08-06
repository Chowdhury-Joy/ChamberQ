<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitMediaService
{
    /**
     * Consultation voice notes and prescription photos are patient clinical
     * records, so they live on the private disk (`storage/app/private`) — not
     * the public one, which is symlinked into the web root and served straight
     * by the web server with no auth. Random UUID filenames are not a
     * substitute: a public URL is a permanent unauthenticated key that leaks
     * through access logs, browser history, and forwarded links, cannot be
     * revoked, and cannot be audited. Every read goes through
     * `VisitMediaController`, which requires a doctor login.
     */
    private const DISK = 'local';

    public const VOICE_MAX_BYTES = 5 * 1024 * 1024;

    public const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

  /**
     * @return list<string>
     */
    public static function allowedVoiceMimeTypes(): array
    {
        return [
            'audio/webm',
            'audio/ogg',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/mpeg',
            'audio/mp3',
            'video/webm',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedPhotoMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
        ];
    }

    public function voiceDirectory(): string
    {
        return 'visit-audio/'.tenant('id');
    }

    public function photoDirectory(): string
    {
        return 'visit-photos/'.tenant('id');
    }

    public function storeVoiceUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'webm';

        $path = $this->voiceDirectory().'/'.Str::uuid().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            ['visibility' => 'private']
        );

        return $path;
    }

    public function storePhotoUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $path = $this->photoDirectory().'/'.Str::uuid().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            ['visibility' => 'private']
        );

        return $path;
    }

    public function deleteIfExists(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Absolute path for streaming through the authenticated controller. There
     * is deliberately no public URL accessor — adding one would recreate the
     * unauthenticated path this disk choice exists to remove.
     */
    public function absolutePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $full = Storage::disk(self::DISK)->path(ltrim($path, '/'));

        return is_file($full) ? $full : null;
    }
}
