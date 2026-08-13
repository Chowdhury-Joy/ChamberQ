<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'medicine_id',
        'medicine_name',
        'generic_name',
        'last_dose',
        'last_frequency',
        'last_duration',
        'last_timing',
        'use_count',
        'last_used_at',
        'hidden_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
