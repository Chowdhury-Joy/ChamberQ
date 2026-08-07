<?php

namespace App\Models;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasUuids;

    protected $fillable = [
        'brand_name',
        'generic_name',
        'default_strength',
        'form',
        'aliases',
        'category',
        'practice_types',
    ];

    protected $casts = [
        'aliases' => 'array',
        'practice_types' => 'array',
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

    public function displayLabel(): string
    {
        $label = mb_strtoupper($this->brand_name);

        if (filled($this->default_strength)) {
            $label .= ' '.$this->default_strength;
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
