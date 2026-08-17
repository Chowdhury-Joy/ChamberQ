<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\VisitRecord;
use App\Support\BdNid;
use App\Support\BdPhone;
use App\Support\SharedClinicalVisit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Load another ChamberQ chamber's clinical history for the same person
 * (NID when set, otherwise normalized phone + name) when both sides have
 * opted into sharing.
 *
 * Results are cached briefly so Consult Screen's 3s poll does not re-query
 * every tick. Voice notes and prescription photos are never included.
 */
class CrossTenantClinicalHistoryService
{
    public const CACHE_SECONDS = 180;

    public const MAX_VISITS = 20;

    /**
     * How far two recorded ages may drift and still be one person.
     *
     * `Patient::displayAge()` ages a stored `age` forward from
     * `age_recorded_at`, so the same person written down at two chambers months
     * apart can round to a year's difference.
     */
    public const AGE_MATCH_TOLERANCE_YEARS = 1;

    public function __construct(
        private readonly PatientService $patientService,
    ) {}

    /**
     * @return Collection<int, SharedClinicalVisit>
     */
    public function sharedVisitsFor(Patient $patient, ?string $viewerUserId = null): Collection
    {
        return $this->payloadFor($patient, $viewerUserId)['visits'];
    }

    /**
     * Visit records from other chambers (media paths stripped), oldest-first callers sort themselves.
     *
     * @return Collection<int, VisitRecord>
     */
    public function sharedVisitRecordsFor(Patient $patient, ?string $viewerUserId = null): Collection
    {
        return $this->sharedVisitsFor($patient, $viewerUserId)
            ->map(fn (SharedClinicalVisit $visit) => $visit->visitRecord)
            ->filter()
            ->values();
    }

    /**
     * Matching remote patient rows (demographics / allergy fields), for warnings.
     *
     * @return Collection<int, Patient>
     */
    public function matchingSharedPatients(Patient $patient, ?string $viewerUserId = null): Collection
    {
        return $this->payloadFor($patient, $viewerUserId)['matches'];
    }

    /**
     * @return array{visits: Collection<int, SharedClinicalVisit>, matches: Collection<int, Patient>}
     */
    private function payloadFor(Patient $patient, ?string $viewerUserId = null): array
    {
        if (! $patient->share_clinical_history) {
            return ['visits' => collect(), 'matches' => collect()];
        }

        $nid = BdNid::normalize($patient->nid);
        $phone = BdPhone::normalize((string) $patient->phone);

        if ($nid === null && ($phone === '' || blank($patient->name))) {
            return ['visits' => collect(), 'matches' => collect()];
        }

        $cacheKey = sprintf(
            'cross_tenant_clinical:%s:%s:%s:%s',
            tenant('id') ?? 'none',
            $patient->id,
            $nid ?? 'no-nid',
            $phone !== '' ? $phone : 'no-phone',
        );

        /** @var array{visits: Collection<int, SharedClinicalVisit>, matches: Collection<int, Patient>} $payload */
        $payload = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($patient, $phone, $nid, $viewerUserId) {
            $matches = $this->findMatchingPatients($patient, $phone, $nid);
            $visits = $this->fetchSharedVisits($matches);

            if ($visits->isNotEmpty() || $matches->isNotEmpty()) {
                Log::info('cross_tenant_clinical_history_fetched', [
                    'viewer_tenant_id' => tenant('id'),
                    'viewer_user_id' => $viewerUserId,
                    'patient_id' => $patient->id,
                    'phone_hash' => $phone !== '' ? hash('sha256', $phone) : null,
                    'phone_last4' => $phone !== '' ? substr($phone, -4) : null,
                    'matched_by_nid' => $nid !== null,
                    'external_visit_count' => $visits->count(),
                    'matching_patient_count' => $matches->count(),
                ]);
            }

            return [
                'visits' => $visits,
                'matches' => $matches,
            ];
        });

        return [
            'visits' => collect($payload['visits'] ?? []),
            'matches' => collect($payload['matches'] ?? []),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $matches
     * @return Collection<int, SharedClinicalVisit>
     */
    private function fetchSharedVisits(Collection $matches): Collection
    {
        if ($matches->isEmpty()) {
            return collect();
        }

        $patientIds = $matches->pluck('id')->all();
        $tenantLabels = $this->tenantLabelsFor($matches->pluck('tenant_id')->unique()->filter()->all());

        $bookings = Booking::withoutGlobalScopes()
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'completed')
            ->with([
                'visitRecord' => function ($query) {
                    $query->withoutGlobalScopes()->with([
                        'condition' => fn ($q) => $q->withoutGlobalScopes(),
                        'prescription' => function ($q) {
                            $q->withoutGlobalScopes()->with([
                                'items' => fn ($items) => $items->withoutGlobalScopes(),
                            ]);
                        },
                    ]);
                },
            ])
            ->orderByDesc('booking_date')
            ->orderByDesc('completed_at')
            ->limit(self::MAX_VISITS)
            ->get();

        return $bookings->map(function (Booking $booking) use ($matches, $tenantLabels) {
            $sourcePatient = $matches->firstWhere('id', $booking->patient_id);
            $tenantId = (string) ($sourcePatient?->tenant_id ?? $booking->tenant_id);
            $visit = $booking->visitRecord;

            if ($visit) {
                // Never expose private media across chambers.
                $visit->voice_path = null;
                $visit->photo_path = null;
                $visit->report_photo_paths = null;
                $visit->setRelation('booking', $booking);

                // These are another chamber's rows, stripped for display only.
                // Consult Screen merges them into the same collection as this
                // chamber's own records, so a later `->save()` is easy to add by
                // accident — and it would write the nulls above back and destroy
                // the real media paths at source. Mark them so the model refuses.
                $visit->markAsForeignChamberRecord();
            }

            $medicines = [];
            foreach ($visit?->prescription?->items ?? [] as $item) {
                $medicines[] = [
                    'brand' => (string) $item->medicine_name,
                    'dose' => $item->dose,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'timing' => $item->timing,
                    'instructions' => $item->instructions,
                ];
            }

            return new SharedClinicalVisit(
                bookingId: (string) $booking->id,
                bookingDate: $booking->booking_date,
                sourceTenantId: $tenantId,
                sourceLabel: $tenantLabels[$tenantId] ?? $tenantId,
                doctorName: null,
                visitRecord: $visit,
                medicines: $medicines,
            );
        })->values();
    }

    /**
     * @return Collection<int, Patient>
     */
    private function findMatchingPatients(Patient $patient, string $phone, ?string $nid): Collection
    {
        $currentTenantId = tenant('id');

        if ($nid !== null) {
            return Patient::withoutGlobalScopes()
                ->where('nid', $nid)
                ->where('share_clinical_history', true)
                ->when($currentTenantId, fn ($q) => $q->where('tenant_id', '!=', $currentTenantId))
                ->whereKeyNot($patient->id)
                ->get()
                ->values();
        }

        $normalizedName = $this->patientService->normalizeName((string) $patient->name);

        return Patient::withoutGlobalScopes()
            ->where('phone', $phone)
            ->where('share_clinical_history', true)
            ->when($currentTenantId, fn ($q) => $q->where('tenant_id', '!=', $currentTenantId))
            ->whereKeyNot($patient->id)
            ->get()
            ->filter(fn (Patient $other) => $this->patientService->normalizeName((string) $other->name) === $normalizedName)
            ->filter(fn (Patient $other) => $this->isSamePerson($patient, $other))
            ->values();
    }

    /**
     * Is this really the same human, or just the same phone and name?
     *
     * One mobile is routinely a whole household's here — the booking wizard's
     * own household picker exists because several `patients` rows share one
     * number — and common names repeat inside a family. Phone + name alone
     * therefore matched relatives to each other, which does not merely leak
     * across chambers: it puts *somebody else's* diagnoses and prescriptions in
     * front of a doctor who is prescribing right now.
     *
     * Age is what actually separates those people (a father and son sharing a
     * name differ by decades), so it is required, and sex is checked whenever
     * both sides recorded it.
     *
     * **Fails closed.** No age on either side means no match. That costs
     * cross-chamber history for chambers that never record an age, which is the
     * correct direction to be wrong about a wrong-patient hazard.
     */
    private function isSamePerson(Patient $local, Patient $other): bool
    {
        $localSex = $this->normalizedSex($local);
        $otherSex = $this->normalizedSex($other);

        if ($localSex !== null && $otherSex !== null && $localSex !== $otherSex) {
            return false;
        }

        // Exact when both sides hold a real date of birth.
        if ($local->date_of_birth && $other->date_of_birth) {
            return $local->date_of_birth->isSameDay($other->date_of_birth);
        }

        $localAge = $local->displayAge();
        $otherAge = $other->displayAge();

        if ($localAge === null || $otherAge === null) {
            return false;
        }

        // `displayAge()` ages a recorded `age` forward from `age_recorded_at`,
        // so two chambers that wrote the same person down months apart can be a
        // year out. Wider than that and they are different people.
        return abs($localAge - $otherAge) <= self::AGE_MATCH_TOLERANCE_YEARS;
    }

    private function normalizedSex(Patient $patient): ?string
    {
        $sex = trim((string) $patient->sex);

        return $sex === '' ? null : mb_strtolower($sex);
    }

    /**
     * @param  list<string|int>  $tenantIds
     * @return array<string, string>
     */
    private function tenantLabelsFor(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get()
            ->mapWithKeys(fn (Tenant $tenant) => [
                $tenant->id => $tenant->displayName(),
            ])
            ->all();
    }
}
