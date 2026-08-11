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
    public const FREE_TEXT_PREFIX = '__free__:';

    public const MIN_SEARCH_LENGTH = 2;

    public const MAX_RESULTS = 20;

    /**
     * Whose practice type the medicine list should follow.
     *
     * A session booking wins — a doctor covering someone else's session
     * prescribes as that session. Otherwise (My medicines, bare search, a lab
     * slot) use the signed-in doctor's own profile, then the single doctor of
     * a solo practice. Staff on a lab booking still resolve to null in a
     * clinic, which fails prescription entry closed.
     */
    public function resolvePrescribingDoctor(?Booking $booking = null): ?Doctor
    {
        if ($booking?->bookable instanceof ScheduleSession) {
            return $booking->bookable->doctor;
        }

        /** @var User|null $user */
        $user = auth()->user();

        if ($user?->isDoctor() && $user->doctorProfile) {
            return $user->doctorProfile;
        }

        $tenant = tenant();

        if ($tenant?->isSoloDoctor()) {
            return Doctor::query()->first();
        }

        return null;
    }

    /**
     * Bounded candidate rows for a search needle.
     *
     * The catalogue is 24,491 SKUs. The previous implementation loaded every
     * row into memory and filtered in PHP on each keystroke, per repeater row —
     * which was survivable at 460 and is not at this size. Matching now happens
     * in SQL, ordered by tier so the candidate window is filled with pinned and
     * curated brands before the long tail, and only the window is hydrated.
     *
     * `practice_types` is a JSON column and cannot be filtered portably in SQL,
     * so `visibleToPracticeType()` still runs in PHP — but over a few hundred
     * rows rather than the whole table.
     *
     * @return Collection<int, Medicine>
     */
    public function catalogCandidates(string $needle, ?string $practiceType, int $window = 300): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $needle).'%';

        return Medicine::query()
            ->where(function ($query) use ($like): void {
                $query->where('brand_name', 'like', $like)
                    ->orWhere('generic_name', 'like', $like)
                    ->orWhere('aliases', 'like', $like);
            })
            ->orderBy('priority')
            ->orderBy('brand_name')
            ->limit($window)
            ->get()
            ->filter(fn (Medicine $medicine) => $medicine->visibleToPracticeType($practiceType));
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

        $hiddenNames = $doctor ? $this->hiddenNameSet($doctor) : [];

        $catalogMatches = $this->catalogCandidates($needle, $practiceType)
            ->map(function (Medicine $medicine) use ($needle, $hiddenNames): ?array {
                if (in_array($this->normalizeMedicineName($medicine->brand_name), $hiddenNames, true)) {
                    return null;
                }

                $matchScore = $this->matchScore($medicine, $needle);

                if ($matchScore === 0) {
                    return null;
                }

                return [
                    'id' => $medicine->id,
                    'medicine_id' => $medicine->id,
                    'brand_name' => mb_strtoupper($medicine->brand_name),
                    'label' => $medicine->displayLabel(),
                    'generic_name' => $medicine->generic_name,
                    'dose' => $medicine->default_strength,
                    'frequency' => null,
                    'duration' => null,
                    'rank' => $matchScore + $this->tierBoost($medicine->priority),
                    'source' => 'catalog',
                ];
            })
            ->filter();

        $usageMatches = collect();

        if ($doctor) {
            $usages = MedicineUsage::query()
                ->where('user_id', $doctor->id)
                ->whereNull('hidden_at')
                ->orderBy('medicine_name')
                ->get();

            // One lookup for every brand on the doctor's list, not one query
            // per matching row inside the loop below. This runs on each
            // keystroke of the prescribing search box, with a patient in the
            // room, so the per-row query it replaces was the worst possible
            // place for an N+1.
            $catalogByBrand = Medicine::query()
                ->whereIn(
                    'brand_name',
                    $usages->map(fn (MedicineUsage $usage) => $this->normalizeMedicineName($usage->medicine_name))
                        ->unique()
                        ->all(),
                )
                ->get()
                ->keyBy(fn (Medicine $medicine) => $this->normalizeMedicineName($medicine->brand_name));

            $usageMatches = $usages
                ->map(function (MedicineUsage $usage) use ($needle, $practiceType, $catalogByBrand): ?array {
                    $normalized = $this->normalizeMedicineName($usage->medicine_name);
                    $matchScore = $this->matchScoreOnTerms([$normalized, mb_strtolower($usage->generic_name ?? '')], $needle);

                    if ($matchScore === 0) {
                        return null;
                    }

                    $catalog = $catalogByBrand->get($normalized);

                    if ($catalog && ! $catalog->visibleToPracticeType($practiceType)) {
                        return null;
                    }

                    return [
                        'id' => self::FREE_TEXT_PREFIX.$normalized,
                        'medicine_id' => $usage->medicine_id,
                        'brand_name' => mb_strtoupper($usage->medicine_name),
                        'label' => mb_strtoupper($usage->medicine_name),
                        'generic_name' => $usage->generic_name,
                        'dose' => $usage->last_dose,
                        'frequency' => $usage->last_frequency,
                        'duration' => $usage->last_duration,
                        // The doctor's own saved entries stay ahead of the
                        // catalogue on an equal text match — they chose these.
                        // That is their curation showing through, not the app
                        // guessing from past consultations.
                        'rank' => $matchScore + 15,
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
            ->orderBy('medicine_name')
            ->limit($limit)
            ->pluck('medicine_name')
            ->map(fn (string $name) => mb_strtoupper($name))
            ->all();
    }

    /**
     * Add or update an entry on a doctor's own **My medicines** list.
     *
     * Only ever called from My medicines, where the doctor is deliberately
     * saving a brand and its default dose / frequency / duration. Nothing
     * writes here as a side effect of prescribing: the app does not watch
     * consultations and infer a shortlist (owner decision, 2026-08-11).
     *
     * `use_count` and `last_used_at` are left untouched — they were the
     * learning counters and no longer mean anything. The columns are still on
     * the table, unused, so the data is not thrown away.
     *
     * @param  array{medicine_name: string, generic_name?: ?string, dose?: ?string, frequency?: ?string, duration?: ?string}  $item
     */
    public function saveDoctorMedicine(User $doctor, array $item): MedicineUsage
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
        $usage->save();

        return $usage;
    }

    /**
     * How far a catalogue tier lifts a row above a same-strength match.
     *
     * This is what replaced leaving rows out of the catalogue entirely. A
     * needle like "ace" matches a hand-verified 500 mg tablet and an IV
     * infusion of the same brand equally well on text alone; the tier is what
     * puts the tablet first. The spread (32 down to 0) is deliberately wider
     * than the gap between a prefix match and a contains match (80 vs 60), so
     * a pinned brand outranks a long-tail row that happens to match better.
     */
    public function tierBoost(?int $priority): int
    {
        return match ($priority) {
            Medicine::TIER_PINNED => 32,
            Medicine::TIER_CURATED => 24,
            Medicine::TIER_ESSENTIAL => 16,
            Medicine::TIER_SPECIALIST => 0,
            default => 8,
        };
    }

    public function normalizeMedicineName(string $name): string
    {
        return mb_strtoupper(trim($name));
    }

    public function resolveMedicineNameFromFormState(array $item): ?string
    {
        $name = $item['medicine_name'] ?? null;

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
