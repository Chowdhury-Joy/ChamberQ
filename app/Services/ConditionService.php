<?php

namespace App\Services;

use App\Models\Condition;
use App\Models\ConditionUsage;
use App\Models\User;
use Illuminate\Support\Collection;

class ConditionService
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_RESULTS = 20;

    /**
     * @return Collection<int, array{id: string, code: string, name: string, label: string, match_score: int, usage_boost: int}>
     */
    public function search(string $query, ?User $doctor = null): Collection
    {
        $needle = $this->normalizeQuery($query);

        if (mb_strlen($needle) < self::MIN_SEARCH_LENGTH) {
            return collect();
        }

        $usageBoosts = $doctor ? $this->usageBoostMap($doctor) : [];

        return Condition::query()
            ->orderBy('name')
            ->get()
            ->map(function (Condition $condition) use ($needle, $usageBoosts) {
                $matchScore = $this->matchScore($condition, $needle);

                if ($matchScore === 0) {
                    return null;
                }

                $usageBoost = $usageBoosts[$condition->id] ?? 0;

                return [
                    'id' => $condition->id,
                    'code' => $condition->code,
                    'name' => $condition->name,
                    'label' => $condition->name,
                    'match_score' => $matchScore,
                    'usage_boost' => $usageBoost,
                    'rank' => $matchScore + $usageBoost,
                ];
            })
            ->filter()
            ->sortByDesc('rank')
            ->take(self::MAX_RESULTS)
            ->values()
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'label' => $row['label'],
            ]);
    }

    /**
     * @return array{
     *     coded: bool,
     *     condition_id: ?string,
     *     code: ?string,
     *     name: string,
     *     label: string
     * }
     */
    public function resolveSelection(?string $conditionId, ?string $freeText): array
    {
        if (filled($conditionId)) {
            $condition = Condition::query()->findOrFail($conditionId);

            return [
                'coded' => true,
                'condition_id' => $condition->id,
                'code' => $condition->code,
                'name' => $condition->name,
                'label' => $condition->name,
            ];
        }

        $label = trim((string) $freeText);

        if ($label === '') {
            throw new \InvalidArgumentException('A coded condition or free-text label is required.');
        }

        return [
            'coded' => false,
            'condition_id' => null,
            'code' => null,
            'name' => $label,
            'label' => $label,
        ];
    }

    public function recordUsage(User $doctor, Condition $condition): ConditionUsage
    {
        $usage = ConditionUsage::query()->firstOrNew([
            'tenant_id' => tenant('id'),
            'user_id' => $doctor->id,
            'condition_id' => $condition->id,
        ]);

        $usage->use_count = ($usage->use_count ?? 0) + 1;
        $usage->last_used_at = now();
        $usage->save();

        return $usage;
    }

    private function normalizeQuery(string $query): string
    {
        return mb_strtolower(trim($query));
    }

    private function matchScore(Condition $condition, string $needle): int
    {
        $best = 0;

        foreach ($condition->searchableTerms() as $term) {
            if ($term === $needle) {
                $best = max($best, 100);
            } elseif (str_starts_with($term, $needle)) {
                $best = max($best, 80);
            } elseif (str_contains($term, $needle)) {
                $best = max($best, 60);
            }
        }

        return $best;
    }

    /**
     * @return array<string, int>
     */
    private function usageBoostMap(User $doctor): array
    {
        return ConditionUsage::query()
            ->where('user_id', $doctor->id)
            ->get()
            ->mapWithKeys(fn (ConditionUsage $usage) => [
                $usage->condition_id => ($usage->use_count * 5) + ($usage->last_used_at?->isAfter(now()->subDays(14)) ? 10 : 0),
            ])
            ->all();
    }
}
