<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasClinicContentFields;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
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
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];
}
