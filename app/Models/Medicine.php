<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'aliases' => 'array',
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

        return $label;
    }
}
