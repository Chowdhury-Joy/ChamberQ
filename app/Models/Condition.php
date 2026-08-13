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
        'default_advice',
        'default_tests',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    /**
     * Starter advice in the language the doctor is currently reading.
     *
     * Falls back to English rather than returning nothing: a doctor on a
     * Bangla panel is still better served by advice he can edit than by an
     * empty box.
     */
    public function adviceForLocale(?string $locale = null): ?string
    {
        if (blank($this->default_advice)) {
            return null;
        }

        $decoded = json_decode((string) $this->default_advice, true);

        if (! is_array($decoded)) {
            return (string) $this->default_advice;
        }

        $locale ??= app()->getLocale();

        return $decoded[$locale] ?? $decoded['en'] ?? reset($decoded) ?: null;
    }

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
