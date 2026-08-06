<?php

namespace App\Services;

use App\Models\Condition;
use App\Models\VisitRecord;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin research aggregates — coded visit counts only, k-anonymity enforced.
 *
 * @see patient-records-plan.md Part 6
 */
class ResearchDataService
{
    /** Minimum group size before a condition count may be shown (k-anonymity). */
    public const MIN_GROUP_SIZE = 10;

    /**
     * Aggregate coded diagnosis counts across all tenants.
     *
     * Filters may narrow the cohort (date range, plan tier) but never slice by
     * individual tenant or patient — only groups with count >= MIN_GROUP_SIZE
     * are returned.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, plan_tier?: ?string}  $filters
     * @return array{
     *     rows: list<array{condition_id: string, condition_code: string, condition_name: string, count: int}>,
     *     suppressed_group_count: int,
     *     total_coded_visits: int,
     * }
     */
    public function conditionCounts(array $filters = []): array
    {
        $query = VisitRecord::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('visit_records.condition_id')
            ->join('tenants', 'visit_records.tenant_id', '=', 'tenants.id');

        if (filled($filters['date_from'] ?? null)) {
            $query->where(
                'visit_records.recorded_at',
                '>=',
                Carbon::parse($filters['date_from'])->startOfDay()
            );
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where(
                'visit_records.recorded_at',
                '<=',
                Carbon::parse($filters['date_to'])->endOfDay()
            );
        }

        if (filled($filters['plan_tier'] ?? null)) {
            $query->where('tenants.plan_tier', $filters['plan_tier']);
        }

        $aggregates = $query
            ->select('visit_records.condition_id', DB::raw('COUNT(*) as visit_count'))
            ->groupBy('visit_records.condition_id')
            ->get();

        $conditions = Condition::query()
            ->whereIn('id', $aggregates->pluck('condition_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        $suppressed = 0;

        foreach ($aggregates as $aggregate) {
            $count = (int) $aggregate->visit_count;

            if ($count < self::MIN_GROUP_SIZE) {
                $suppressed++;

                continue;
            }

            $condition = $conditions->get($aggregate->condition_id);

            $rows[] = [
                'condition_id' => (string) $aggregate->condition_id,
                'condition_code' => $condition?->code ?? '—',
                'condition_name' => $condition?->name ?? 'Unknown condition',
                'count' => $count,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return [
            'rows' => $rows,
            'suppressed_group_count' => $suppressed,
            'total_coded_visits' => (int) $aggregates->sum('visit_count'),
        ];
    }
}
