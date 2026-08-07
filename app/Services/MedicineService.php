<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\MedicineUsage;
use App\Models\ScheduleSession;
use App\Models\User;
use Illuminate\Support\Collection;

class MedicineService
{
    public const MIN_SEARCH_LENGTH = 2;

    public const MAX_RESULTS = 20;

    public const FREE_TEXT_PREFIX = '__free__:';

    public const CUSTOM_MEDICINE_VALUE = '__custom__';

    public function resolvePrescribingDoctor(?Booking $booking = null): ?Doctor
    {
        if ($booking?->bookable instanceof ScheduleSession) {
            return $booking->bookable->doctor;
        }

        $tenant = tenant();

        if ($tenant?->isSoloDoctor()) {
            return Doctor::query()->first();
        }

        return null;
    }

    /**
     * Grouped options for prescription / My medicines dropdowns.
     *
     * @return array<string, array<string, string>>
     */
    public function groupedSelectOptions(?User $doctor, ?Doctor $prescribingDoctor = null): array
    {
        $prescribingDoctor ??= $this->resolvePrescribingDoctor();
        $practiceType = $prescribingDoctor?->practice_type ?? Doctor::PRACTICE_GENERAL;

        $groups = [];

        if ($doctor) {
            $personal = $this->personalMedicineOptions($doctor, $practiceType);

            if ($personal !== []) {
                $groups[__('Your medicines')] = $personal;
            }
        }

        foreach ($this->catalogMedicinesForPracticeType($practiceType) as $category => $options) {
            $groups[$category] = $options;
        }

        $groups[__('Other')] = [
            self::CUSTOM_MEDICINE_VALUE => __('Other medicine — type below'),
        ];

        return $groups;
    }

    /**
     * @return Collection<int, Medicine>
     */
    public function catalogForPracticeType(?string $practiceType): Collection
    {
        return Medicine::query()
            ->orderBy('category')
            ->orderBy('brand_name')
            ->get()
            ->filter(fn (Medicine $medicine) => $medicine->visibleToPracticeType($practiceType));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function catalogMedicinesForPracticeType(?string $practiceType): array
    {
        $grouped = [];

        foreach ($this->catalogForPracticeType($practiceType) as $medicine) {
            $category = filled($medicine->category) ? $medicine->category : __('General');
            $grouped[$category][$medicine->brand_name] = $medicine->displayLabel();
        }

        return $grouped;
    }

    /**
     * @return array<string, string>
     */
    private function personalMedicineOptions(User $doctor, ?string $practiceType): array
    {
        $hidden = $this->hiddenNameSet($doctor);
        $options = [];

        foreach (MedicineUsage::query()
            ->where('user_id', $doctor->id)
            ->whereNull('hidden_at')
            ->orderByDesc('use_count')
            ->orderByDesc('last_used_at')
            ->get() as $usage) {
            $brand = $this->normalizeMedicineName($usage->medicine_name);

            if (in_array($brand, $hidden, true)) {
                continue;
            }

            $catalog = Medicine::query()->where('brand_name', $brand)->first();

            if ($catalog && ! $catalog->visibleToPracticeType($practiceType)) {
                continue;
            }

            $options[$brand] = $brand;
        }

        return $options;
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     medicine_id: ?string,
     *     brand_name: string,
     *     label: string,
     *     generic_name: ?string,
     *     dose: ?string,
     *     frequency: ?string,
     *     duration: ?string,
     *     rank: int,
     *     source: string
     * }>
     */
    public function search(string $query, ?User $doctor = null, ?Doctor $prescribingDoctor = null): Collection
    {
        $needle = $this->normalizeQuery($query);

        if (mb_strlen($needle) < self::MIN_SEARCH_LENGTH) {
            return collect();
        }

        $prescribingDoctor ??= $this->resolvePrescribingDoctor();
        $practiceType = $prescribingDoctor?->practice_type ?? Doctor::PRACTICE_GENERAL;

        $usageBoosts = $doctor ? $this->usageBoostMap($doctor) : [];
        $hiddenNames = $doctor ? $this->hiddenNameSet($doctor) : [];

        $catalogMatches = $this->catalogForPracticeType($practiceType)
            ->map(function (Medicine $medicine) use ($needle, $usageBoosts, $hiddenNames): ?array {
                if (in_array($this->normalizeMedicineName($medicine->brand_name), $hiddenNames, true)) {
                    return null;
                }

                $matchScore = $this->matchScore($medicine, $needle);

                if ($matchScore === 0) {
                    return null;
                }

                $usageBoost = $usageBoosts[$this->normalizeMedicineName($medicine->brand_name)] ?? 0;

                return [
                    'id' => $medicine->id,
                    'medicine_id' => $medicine->id,
                    'brand_name' => mb_strtoupper($medicine->brand_name),
                    'label' => $medicine->displayLabel(),
                    'generic_name' => $medicine->generic_name,
                    'dose' => $medicine->default_strength,
                    'frequency' => null,
                    'duration' => null,
                    'rank' => $matchScore + $usageBoost,
                    'source' => 'catalog',
                ];
            })
            ->filter();

        $usageMatches = collect();

        if ($doctor) {
            $usageMatches = MedicineUsage::query()
                ->where('user_id', $doctor->id)
                ->whereNull('hidden_at')
                ->get()
                ->map(function (MedicineUsage $usage) use ($needle, $usageBoosts, $practiceType): ?array {
                    $normalized = $this->normalizeMedicineName($usage->medicine_name);
                    $matchScore = $this->matchScoreOnTerms([$normalized, mb_strtolower($usage->generic_name ?? '')], $needle);

                    if ($matchScore === 0) {
                        return null;
                    }

                    $catalog = Medicine::query()->where('brand_name', $normalized)->first();

                    if ($catalog && ! $catalog->visibleToPracticeType($practiceType)) {
                        return null;
                    }

                    $usageBoost = $usageBoosts[$normalized] ?? 0;

                    return [
                        'id' => self::FREE_TEXT_PREFIX.$normalized,
                        'medicine_id' => $usage->medicine_id,
                        'brand_name' => mb_strtoupper($usage->medicine_name),
                        'label' => mb_strtoupper($usage->medicine_name),
                        'generic_name' => $usage->generic_name,
                        'dose' => $usage->last_dose,
                        'frequency' => $usage->last_frequency,
                        'duration' => $usage->last_duration,
                        'rank' => $matchScore + $usageBoost + 15,
                        'source' => 'usage',
                    ];
                })
                ->filter();
        }

        return $catalogMatches
            ->concat($usageMatches)
            ->sortByDesc('rank')
            ->unique(fn (array $row) => $this->normalizeMedicineName($row['brand_name']))
            ->take(self::MAX_RESULTS)
            ->values()
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'medicine_id' => $row['medicine_id'],
                'brand_name' => $row['brand_name'],
                'label' => $row['label'],
                'generic_name' => $row['generic_name'],
                'dose' => $row['dose'],
                'frequency' => $row['frequency'],
                'duration' => $row['duration'],
                'source' => $row['source'],
            ]);
    }

    /**
     * @return list<string>
     */
    public function vocabularyHints(?User $doctor, int $limit = 40): array
    {
        if (! $doctor) {
            return Medicine::query()
                ->orderBy('brand_name')
                ->limit($limit)
                ->pluck('brand_name')
                ->map(fn (string $name) => mb_strtoupper($name))
                ->all();
        }

        return MedicineUsage::query()
            ->where('user_id', $doctor->id)
            ->whereNull('hidden_at')
            ->orderByDesc('use_count')
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->pluck('medicine_name')
            ->map(fn (string $name) => mb_strtoupper($name))
            ->all();
    }

    /**
     * @param  array{medicine_name: string, generic_name?: ?string, dose?: ?string, frequency?: ?string, duration?: ?string}  $item
     */
    public function recordUsage(User $doctor, array $item): MedicineUsage
    {
        $name = $this->normalizeMedicineName($item['medicine_name']);

        $usage = MedicineUsage::query()->firstOrNew([
            'tenant_id' => tenant('id'),
            'user_id' => $doctor->id,
            'medicine_name' => $name,
        ]);

        $usage->medicine_id = $item['medicine_id'] ?? $usage->medicine_id;
        $usage->generic_name = filled($item['generic_name'] ?? null)
            ? trim((string) $item['generic_name'])
            : $usage->generic_name;
        $usage->last_dose = filled($item['dose'] ?? null) ? trim((string) $item['dose']) : $usage->last_dose;
        $usage->last_frequency = filled($item['frequency'] ?? null) ? trim((string) $item['frequency']) : $usage->last_frequency;
        $usage->last_duration = filled($item['duration'] ?? null) ? trim((string) $item['duration']) : $usage->last_duration;
        $usage->use_count = ($usage->use_count ?? 0) + 1;
        $usage->last_used_at = now();
        $usage->save();

        return $usage;
    }

    public function normalizeMedicineName(string $name): string
    {
        return mb_strtoupper(trim($name));
    }

    public function resolveMedicineNameFromFormState(array $item): ?string
    {
        $name = $item['medicine_name'] ?? null;

        if ($name === self::CUSTOM_MEDICINE_VALUE) {
            $name = $item['medicine_name_custom'] ?? null;
        }

        return filled($name) ? $this->normalizeMedicineName((string) $name) : null;
    }

    private function normalizeQuery(string $query): string
    {
        return mb_strtolower(trim($query));
    }

    private function matchScore(Medicine $medicine, string $needle): int
    {
        return $this->matchScoreOnTerms($medicine->searchableTerms(), $needle);
    }

    /**
     * @param  list<string>  $terms
     */
    private function matchScoreOnTerms(array $terms, string $needle): int
    {
        $best = 0;

        foreach ($terms as $term) {
            $term = mb_strtolower(trim($term));

            if ($term === '') {
                continue;
            }

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
        return MedicineUsage::query()
            ->where('user_id', $doctor->id)
            ->whereNull('hidden_at')
            ->get()
            ->mapWithKeys(fn (MedicineUsage $usage) => [
                $this->normalizeMedicineName($usage->medicine_name) => ($usage->use_count * 5)
                    + ($usage->last_used_at?->isAfter(now()->subDays(14)) ? 10 : 0),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function hiddenNameSet(User $doctor): array
    {
        return MedicineUsage::query()
            ->where('user_id', $doctor->id)
            ->whereNotNull('hidden_at')
            ->pluck('medicine_name')
            ->map(fn (string $name) => $this->normalizeMedicineName($name))
            ->all();
    }
}
