<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitMediaService
{
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

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $path;
    }

    public function storePhotoUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $path = $this->photoDirectory().'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $path;
    }

    public function deleteIfExists(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function absolutePublicPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($full) ? $full : null;
    }
}
