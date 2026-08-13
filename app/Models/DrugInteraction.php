<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An ingredient pair that should not normally be prescribed together.
 *
 * Tenant-agnostic — pharmacology does not vary by chamber. Pairs are stored
 * with `ingredient_a` <= `ingredient_b` alphabetically so a lookup never
 * depends on the order the doctor typed the two drugs in.
 */
class DrugInteraction extends Model
{
    use HasUuids;

    /** Should not be prescribed together at all. */
    public const SEVERITY_AVOID = 'avoid';

    /** Can be prescribed together, but needs a deliberate decision. */
    public const SEVERITY_SERIOUS = 'serious';

    /** @var list<string> */
    public const SEVERITIES = [self::SEVERITY_AVOID, self::SEVERITY_SERIOUS];

    protected $fillable = [
        'ingredient_a',
        'ingredient_b',
        'severity',
        'effect',
        'action',
        'source',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * When this pair was last checked — never *by whom*.
     *
     * The reviewer name was removed by owner decision (2026-08-12): no
     * clinician is named against clinical content anywhere in the product,
     * because that makes one person personally answerable for a list the
     * practice ships. What the name was protecting against is handled instead
     * by `RxSafety::DISCLAIMER`, which every doctor sees beside every warning.
     *
     * A date with no name still answers the only question worth asking of a
     * reference list: how old is it.
     */
    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }
}
