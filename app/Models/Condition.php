<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Condition extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'aliases',
        'category',
        'icd10_unverified',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(ConditionUsage::class);
    }

    /**
     * @return list<string>
     */
    public function searchableTerms(): array
    {
        $terms = array_filter([
            $this->name,
            $this->code,
            ...($this->aliases ?? []),
        ]);

        return array_values(array_unique(array_map(
            fn (string $term) => mb_strtolower(trim($term)),
            $terms
        )));
    }
}
