<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Advice or History chip a doctor keeps on the Rx desk.
 *
 * A row with a `default_key` overrides (or hides) the built-in chip of that
 * key; a row without one is a chip the doctor added.
 */
class DoctorChip extends Model
{
    use BelongsToTenant;

    public const KIND_ADVICE = 'advice';

    public const KIND_HISTORY = 'history';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'kind',
        'default_key',
        'label',
        'text_bn',
        'is_primary',
        'hidden_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * What the chip puts into the box.
     *
     * Advice chips carry a Bangla line because the patient reads the advice
     * box while the doctor reads the button. When the doctor gave only one of
     * the two, that one does both jobs.
     */
    public function insertedText(): string
    {
        return filled($this->text_bn) ? (string) $this->text_bn : (string) $this->label;
    }
}
