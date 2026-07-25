<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebPage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'content',
        'is_published',
    ];

    protected $casts = [
        'content' => 'array',
        'is_published' => 'boolean',
    ];
}
