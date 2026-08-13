<?php

namespace App\Filament\TenantAdmin\Support;

use App\Support\PublicStoredImage;
use Filament\Forms\Components\FileUpload;
use League\Flysystem\UnableToCheckFileExistence;

final class PublicMediaFields
{
    public static function image(string $name, string $directoryPrefix, string $label, string $helper): FileUpload
    {
        return self::base($name, $directoryPrefix, $label, $helper)
            ->image()
            ->maxSize(4096)
            ->imagePreviewHeight('16rem');
    }

    public static function video(string $name, string $directoryPrefix, string $label, string $helper): FileUpload
    {
        return self::base($name, $directoryPrefix, $label, $helper)
            ->acceptedFileTypes([
                'video/mp4',
                'video/webm',
                'video/quicktime',
            ])
            ->maxSize(20480);
    }

    private static function base(string $name, string $directoryPrefix, string $label, string $helper): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->helperText($helper)
            ->disk('public')
            ->directory(fn (): string => $directoryPrefix.'/'.(tenant('id') ?? 'shared'))
            ->visibility('public')
            ->fetchFileInformation(false)
            ->openable()
            ->getUploadedFileUsing(function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
                    return [
                        'name' => basename(parse_url($file, PHP_URL_PATH) ?: 'file'),
                        'size' => 0,
                        'type' => null,
                        'url' => $file,
                    ];
                }

                $diskPath = PublicStoredImage::toDiskPath($file) ?? $file;

                try {
                    if ($component->getDisk()->exists($diskPath)) {
                        return $component->getUploadedFile($diskPath, $storedFileNames);
                    }
                } catch (UnableToCheckFileExistence) {
                    return null;
                }

                return null;
            })
            ->deleteUploadedFileUsing(function (FileUpload $component, string $file): void {
                $diskPath = PublicStoredImage::toDiskPath($file);

                if ($diskPath && $component->getDisk()->exists($diskPath)) {
                    $component->getDisk()->delete($diskPath);
                }
            });
    }
}
