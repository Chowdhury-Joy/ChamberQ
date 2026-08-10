<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasClinicContentFields;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use BelongsToTenant;
    use HasClinicContentFields;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'image_url',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function displayDate(): string
    {
        return ($this->published_at ?? $this->created_at)?->format('M j, Y') ?? '';
    }
}
