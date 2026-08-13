<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\HtmlSanitizer;
use App\Support\PublicStoredImage;
use App\Support\SafeUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class WebPage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_published',
    ];

    protected $casts = [
        'content' => 'array',
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Sanitise on the way in, not on the way out: the stored value is then
        // trustworthy for every consumer, and no future template can forget to
        // escape it. rich_text blocks are the only ones rendered unescaped.
        static::saving(function (self $page) {
            $content = $page->content;

            if (! is_array($content)) {
                return;
            }

            foreach ($content as $index => $block) {
                if (! is_array($block)) {
                    continue;
                }

                if (($block['type'] ?? null) === 'rich_text' && isset($block['data']['content'])) {
                    $block['data']['content'] = HtmlSanitizer::clean($block['data']['content']);
                }

                $block = self::promoteUploadedVideos($block);
                $block = PublicStoredImage::promoteBuilderBlock($block);
                $content[$index] = SafeUrl::sanitizeBuilderBlock($block);
            }

            $page->content = $content;
        });
    }

    /**
     * Normalise the stored slug so '/about', 'about' and '/about/' all resolve.
     */
    public function setSlugAttribute(?string $value): void
    {
        $value = trim((string) $value);
        $value = '/' . trim($value, '/');

        $this->attributes['slug'] = $value;
    }

    /**
     * Direct video uploads live in `uploaded_video`; the homepage cards still
     * read `video_url`, so copy the public path across on save.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function promoteUploadedVideos(array $block): array
    {
        if (($block['type'] ?? null) !== 'video_gallery') {
            return $block;
        }

        $videos = $block['data']['videos'] ?? null;

        if (! is_array($videos)) {
            return $block;
        }

        foreach ($videos as $index => $video) {
            if (! is_array($video) || ($video['type'] ?? '') !== 'upload') {
                continue;
            }

            $file = $video['uploaded_video'] ?? null;

            if (is_array($file)) {
                $file = Arr::first($file);
            }

            if (! is_string($file) || trim($file) === '') {
                continue;
            }

            $videos[$index]['video_url'] = PublicStoredImage::toPublicPath($file);
        }

        $block['data']['videos'] = $videos;

        return $block;
    }
}
