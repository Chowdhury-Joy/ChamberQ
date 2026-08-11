<?php

namespace App\Services;

use App\Models\Condition;
use App\Models\User;
use Illuminate\Support\Collection;

class ConditionService
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_RESULTS = 20;

    /**
     * Coded conditions matching the query, best text match first.
     *
     * Deliberately does not rank by what this doctor has diagnosed before: the
     * app does not learn from consultations (owner decision, 2026-08-11). The
     * `$doctor` argument is kept so callers and the route signature are
     * unchanged, and so a future *explicitly curated* shortlist has an obvious
     * place to hook in.
     *
     * @return Collection<int, array{id: string, code: string, name: string, label: string}>
     */
    public function search(string $query, ?User $doctor = null): Collection
    {
        $needle = $this->normalizeQuery($query);

        if (mb_strlen($needle) < self::MIN_SEARCH_LENGTH) {
            return collect();
        }

        return Condition::query()
            ->orderBy('name')
            ->get()
            ->map(function (Condition $condition) use ($needle) {
                $matchScore = $this->matchScore($condition, $needle);

                if ($matchScore === 0) {
                    return null;
                }

                return [
                    'id' => $condition->id,
                    'code' => $condition->code,
                    'name' => $condition->name,
                    'label' => $condition->name,
                    'match_score' => $matchScore,
                ];
            })
            ->filter()
            // Ties keep the alphabetical order the query already applied.
            ->sortByDesc('match_score')
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

}
