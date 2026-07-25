<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

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
                if (($block['type'] ?? null) === 'rich_text' && isset($block['data']['content'])) {
                    $content[$index]['data']['content'] = HtmlSanitizer::clean($block['data']['content']);
                }
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
}
