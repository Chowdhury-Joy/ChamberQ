<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public const REPORT_PHOTO_MAX_FILES = 8;

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

    public function reportPhotoDirectory(): string
    {
        return 'visit-reports/'.tenant('id');
    }

    /**
     * A stored media path must live under this practice's own directory.
     *
     * Every one of these columns (`voice_path`, `photo_path`,
     * `report_photo_paths`) is written from form state the browser sends, so
     * without this a doctor could point a visit record at another chamber's
     * file and have the authenticated stream controller hand it over. `..` and
     * null bytes are rejected outright rather than relied on Flysystem to
     * catch.
     */
    public function isOwnedMediaPath(?string $path, string $directory): bool
    {
        if (blank($path) || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        return str_starts_with($path, rtrim($directory, '/').'/');
    }

    public function isOwnedVoicePath(?string $path): bool
    {
        return $this->isOwnedMediaPath($path, $this->voiceDirectory());
    }

    public function isOwnedPhotoPath(?string $path): bool
    {
        return $this->isOwnedMediaPath($path, $this->photoDirectory());
    }

    public function isOwnedReportPhotoPath(?string $path): bool
    {
        return $this->isOwnedMediaPath($path, $this->reportPhotoDirectory());
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

    public function storeReportPhotoUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $path = $this->reportPhotoDirectory().'/'.Str::uuid().'.'.$extension;

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

        if (
            ! $this->isOwnedVoicePath($path)
            && ! $this->isOwnedPhotoPath($path)
            && ! $this->isOwnedReportPhotoPath($path)
        ) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Whether a stored file still exists, for the controller to 404 on.
     *
     * There is deliberately no public URL accessor — adding one would
     * recreate the unauthenticated path this disk choice exists to remove.
     */
    public function exists(?string $path): bool
    {
        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Stream a stored file through the authenticated controller.
     *
     * Goes through `Storage::disk()->response()`, which reads via Flysystem
     * rather than resolving a real filesystem path — `absolutePath()` (now
     * removed) called `Storage::disk()->path()` and `response()->file()`,
     * both of which only work on the `local` driver. `config/filesystems.php`
     * already ships an `s3` disk, but nothing pointed clinical media at it: had
     * the disk ever been switched, this controller would have started
     * throwing on every request. `response()` works identically on `local`
     * and any Flysystem-backed disk (`s3`, etc.), so moving `DISK` is a config
     * change, not a code change.
     */
    public function streamResponse(?string $path): ?StreamedResponse
    {
        if (! $this->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->response($path);
    }
}
