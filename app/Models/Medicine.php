<?php

namespace App\Models;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasUuids;

    /** Catalogue tiers — lower sorts first in every picker and search. */
    public const TIER_PINNED = 0;

    public const TIER_CURATED = 1;

    public const TIER_ESSENTIAL = 2;

    public const TIER_STANDARD = 3;

    /** Parenteral, chemo and vaccines: real, but never a chamber's first hit. */
    public const TIER_SPECIALIST = 4;

    protected $fillable = [
        'brand_name',
        'generic_name',
        'default_strength',
        'form',
        'aliases',
        'category',
        'practice_types',
        'indications',
        'manufacturer',
        'is_essential',
        'priority',
    ];

    protected $casts = [
        'aliases' => 'array',
        'practice_types' => 'array',
        'is_essential' => 'boolean',
        'priority' => 'integer',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(MedicineUsage::class);
    }

    /**
     * @return list<string>
     */
    public function searchableTerms(): array
    {
        $terms = array_filter([
            $this->brand_name,
            $this->generic_name,
            ...($this->aliases ?? []),
        ]);

        return array_values(array_unique(array_map(
            fn (string $term) => mb_strtolower(trim($term)),
            $terms
        )));
    }

    /**
     * Brand, strength, **form**, then generic.
     *
     * The form is not decoration. The catalogue now holds one row per
     * brand + strength + form, so NAPA ships a 500 mg tablet *and* a 500 mg
     * suppository — without the form in the label those two are the same
     * string, and a doctor picking from a list would have no way to tell
     * which one they just prescribed. The generic stays in the label because
     * a searchable static select filters on the rendered text (see
     * `bug_history.md`: typing "omeprazole" must still find OMEE).
     */
    public function displayLabel(): string
    {
        $label = mb_strtoupper($this->brand_name);

        if (filled($this->default_strength)) {
            $label .= ' '.$this->default_strength;
        }

        if (filled($this->form)) {
            $label .= ' '.$this->form;
        }

        if (filled($this->generic_name)) {
            $label .= ' — '.$this->generic_name;
        }

        return $label;
    }

    public function visibleToPracticeType(?string $practiceType): bool
    {
        $types = $this->practice_types ?? [];

        if ($types === [] || $practiceType === null) {
            return true;
        }

        if ($practiceType === Doctor::PRACTICE_GENERAL) {
            return true;
        }

        return in_array($practiceType, $types, true);
    }
}
