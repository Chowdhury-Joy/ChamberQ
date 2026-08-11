<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retired — nothing reads or writes this.
 *
 * These rows counted how often each doctor picked each coded condition, and
 * ranked the diagnosis picker by it. Automatic learning was removed on
 * 2026-08-11 by owner decision: doctors curate **My medicines** themselves and
 * the app does not infer preferences from consultations. There is no curated
 * equivalent for diagnoses, so the diagnosis picker now ranks on text match
 * alone and this table has no writer left.
 *
 * The model is kept because the `condition_usages` table still exists and holds
 * historical rows (no destructive migration was run) — it is the documented way
 * to reach them, and it keeps the table in chamber backups. Do not re-point
 * anything at it to bring ranking back without an explicit owner decision.
 */
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
