<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\Tenant;
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
     * @return Collection<int, \App\Models\VisitRecord>
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
                    'phone' => $phone,
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
            ->values();
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
