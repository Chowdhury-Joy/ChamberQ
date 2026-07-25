<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Chamber extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'hours',
        'contact',
    ];

    protected $casts = [
        'hours' => 'array',
    ];
}
