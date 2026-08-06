<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'condition_id',
        'use_count',
        'last_used_at',
    ];

    protected $casts = [
        'use_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
