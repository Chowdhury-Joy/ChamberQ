<?php

namespace App\Models\Concerns;

use App\Support\HtmlSanitizer;
use App\Support\SafeUrl;
use Illuminate\Support\Str;

trait HasClinicContentFields
{
    protected static function bootHasClinicContentFields(): void
    {
        static::saving(function (self $model): void {
            if (filled($model->slug)) {
                $model->slug = Str::slug($model->slug);
            } elseif (filled($model->title)) {
                $model->slug = Str::slug($model->title);
            }

            if (filled($model->body)) {
                $model->body = HtmlSanitizer::clean($model->body);
            }

            if (filled($model->image_url)) {
                $model->image_url = SafeUrl::href($model->image_url, '');
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }
}
