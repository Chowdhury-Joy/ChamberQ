<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prescription a doctor saved to reuse — his "pack" for a diagnosis.
 *
 * Built and edited on **My medicines**, never on the consult screen — naming
 * and assembling a set of medicines is preparation, not something a doctor
 * does with a patient in the chair (owner decision, 2026-08-12). The Rx desk
 * applies packs and cannot create them.
 *
 * Nothing derives one by watching consultations, and none ship with the
 * product: a drug set attached to a diagnosis is a clinical recommendation,
 * and that is the doctor's to make.
 */
class PrescriptionTemplate extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'condition_id',
        'advice',
        'tests_advised',
        'follow_up_relative',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionTemplateItem::class)->orderBy('sort_order');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
