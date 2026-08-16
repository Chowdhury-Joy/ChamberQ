<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Models\LiveSession;
use App\Models\VisitRecord;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitRecordService
{
    public function __construct(
        private readonly ConditionService $conditionService,
        private readonly MedicineService $medicineService,
        private readonly VisitMediaService $visitMediaService,
    ) {}

    /**
     * Save optional visit notes when a doctor completes a booking.
     * Returns null when every field was left blank (honest "no notes" state).
     *
     * Nothing is inferred from what gets prescribed here. Doctors curate their
     * own shortlist in **My medicines**; the app does not watch consultations
     * and build one for them (owner decision, 2026-08-11).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveForCompletedBooking(Booking $booking, User $doctor, array $data): ?VisitRecord
    {
        if (! $doctor->canRecordVisitNotes()) {
            abort(403);
        }

        $data = VisitNotesFormSchema::normalizeSubmission($data);

        if (! $this->submissionHasContent($data)) {
            return null;
        }

        return DB::transaction(function () use ($booking, $doctor, $data) {
            $diagnosis = $this->resolveDiagnosis($data);

            $existing = VisitRecord::query()->where('booking_id', $booking->id)->first();
            $voicePath = $this->nullableString($data['voice_path'] ?? null);
            $photoPath = $this->normalizeUploadedPath($data['prescription_photo'] ?? null);
            $reportPhotos = array_key_exists('report_photos', $data)
                ? $this->normalizeReportPhotoPaths($data['report_photos'])
                : ($existing?->report_photo_paths);
            $voiceTranscript = $this->nullableString($data['voice_transcript'] ?? null);

            if ($existing && filled($existing->voice_path) && $voicePath !== $existing->voice_path) {
                $this->visitMediaService->deleteIfExists($existing->voice_path);
            }

            if ($existing && filled($existing->photo_path) && $photoPath !== $existing->photo_path) {
                $this->visitMediaService->deleteIfExists($existing->photo_path);
            }

            $this->syncReportPhotos($existing?->report_photo_paths, $reportPhotos);

            $visitRecord = VisitRecord::query()->updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'tenant_id' => tenant('id'),
                    'patient_id' => $booking->patient_id,
                    'recorded_by' => $doctor->id,
                    'condition_id' => $diagnosis['condition_id'],
                    'diagnosis_uncoded' => $diagnosis['coded'] ? null : $diagnosis['name'],
                    'weight_kg' => $data['weight_kg'] ?? null,
                    'bp_systolic' => $data['bp_systolic'] ?? null,
                    'bp_diastolic' => $data['bp_diastolic'] ?? null,
                    'pulse_bpm' => $data['pulse_bpm'] ?? null,
                    'spo2_percent' => $data['spo2_percent'] ?? null,
                    'temperature_f' => $data['temperature_f'] ?? null,
                    'clinical_notes' => $this->nullableString($data['clinical_notes'] ?? null),
                    'chief_complaint' => $this->nullableString($data['chief_complaint'] ?? null),
                    'history' => $this->nullableString($data['history'] ?? null),
                    'on_examination' => $this->nullableString($data['on_examination'] ?? null),
                    'advice' => $this->nullableString($data['advice'] ?? null),
                    'tests_advised' => $this->nullableString($data['tests_advised'] ?? null),
                    'reports_seen' => $this->nullableString($data['reports_seen'] ?? null),
                    'report_photo_paths' => $reportPhotos,
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'follow_up_note' => $this->nullableString($data['follow_up_note'] ?? null),
                    'voice_path' => $voicePath,
                    'photo_path' => $photoPath,
                    'voice_transcript' => $voiceTranscript,
                    'recorded_at' => now(),
                ]
            );

            $this->syncPrescription($visitRecord, $doctor, $data);

            return $visitRecord->fresh(['condition', 'prescription.items']);
        });
    }

    /**
     * Staff typing up a prescription the doctor wrote on paper.
     *
     * Writes only the prescription, follow-up, paper photo and photos of
     * reports the patient brought. Any diagnosis, vitals, clinical notes,
     * advice, tests, typed reports note or voice note already on the record
     * is left untouched — staff never overwrite the doctor's clinical notes,
     * and anything they submit outside the whitelist is discarded.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveStaffEnteredPrescription(Booking $booking, User $staff, array $data): ?VisitRecord
    {
        $prescribingDoctor = $this->medicineService->resolvePrescribingDoctor($booking);

        if (! $staff->canEnterPrescriptionFor($prescribingDoctor)) {
            abort(403);
        }

        $data = VisitNotesFormSchema::normalizeSubmission(
            array_intersect_key($data, array_flip(VisitNotesFormSchema::STAFF_WRITABLE_FIELDS))
        );

        if (! $this->submissionHasContent($data)) {
            return null;
        }

        return DB::transaction(function () use ($booking, $staff, $data) {
            $existing = VisitRecord::query()->where('booking_id', $booking->id)->first();
            $photoPath = $this->normalizeUploadedPath($data['prescription_photo'] ?? null);
            $reportPhotos = array_key_exists('report_photos', $data)
                ? $this->normalizeReportPhotoPaths($data['report_photos'])
                : ($existing?->report_photo_paths);

            if ($existing && filled($existing->photo_path) && $photoPath !== $existing->photo_path) {
                $this->visitMediaService->deleteIfExists($existing->photo_path);
            }

            $this->syncReportPhotos($existing?->report_photo_paths, $reportPhotos);

            $visitRecord = VisitRecord::query()->updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'tenant_id' => tenant('id'),
                    'patient_id' => $booking->patient_id,
                    // Who actually keyed it. The prescriber stays derivable
                    // from the booking's session, so the paper trail shows both.
                    'recorded_by' => $staff->id,
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'follow_up_note' => $this->nullableString($data['follow_up_note'] ?? null),
                    'photo_path' => $photoPath,
                    'report_photo_paths' => $reportPhotos,
                    'recorded_at' => now(),
                ]
            );

            $this->syncPrescription($visitRecord, $staff, $data);

            return $visitRecord->fresh(['condition', 'prescription.items']);
        });
    }

    /**
     * Outdoor BP/weight before the doctor — staff only, waiting rows.
     *
     * @param  array{weight_kg?: mixed, bp_systolic?: mixed, bp_diastolic?: mixed}  $data
     */
    public function saveStaffVitals(Booking $booking, User $staff, array $data): ?VisitRecord
    {
        if (! tenant()?->hasStations()) {
            abort(403);
        }

        if (! $staff->canWorkDesk()) {
            abort(403);
        }

        $weight = isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (float) $data['weight_kg'] : null;
        $sys = isset($data['bp_systolic']) && $data['bp_systolic'] !== '' ? (int) $data['bp_systolic'] : null;
        $dia = isset($data['bp_diastolic']) && $data['bp_diastolic'] !== '' ? (int) $data['bp_diastolic'] : null;

        if ($weight === null && $sys === null && $dia === null) {
            return null;
        }

        if (($sys === null) !== ($dia === null)) {
            throw new \InvalidArgumentException(__('Enter both BP numbers, or leave both blank.'));
        }

        return VisitRecord::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'tenant_id' => tenant('id'),
                'patient_id' => $booking->patient_id,
                'recorded_by' => $staff->id,
                'weight_kg' => $weight,
                'bp_systolic' => $sys,
                'bp_diastolic' => $dia,
                'recorded_at' => now(),
            ],
        );
    }

    public function lastRecordedVisitForPatient(Patient $patient, ?string $excludeBookingId = null): ?VisitRecord
    {
        return VisitRecord::query()
            ->where('patient_id', $patient->id)
            ->when($excludeBookingId, fn ($query) => $query->where('booking_id', '!=', $excludeBookingId))
            ->whereHas('booking', fn ($query) => $query->where('status', 'completed'))
            ->with(['condition', 'prescription.items', 'booking'])
            ->get()
            ->filter(fn (VisitRecord $record) => $record->hasClinicalContent())
            ->sortByDesc(fn (VisitRecord $record) => $record->booking?->completed_at ?? $record->recorded_at)
            ->first();
    }

    public function patientHasRecordedNotes(Patient $patient): bool
    {
        return VisitRecord::query()
            ->where('patient_id', $patient->id)
            ->whereHas('booking', fn ($query) => $query->where('status', 'completed'))
            ->with(['prescription'])
            ->get()
            ->contains(fn (VisitRecord $record) => $record->hasClinicalContent());
    }

    /**
     * Does this submission hold anything worth keeping?
     *
     * Callers ask this *before* completing the booking and advancing the queue,
     * so this must always answer and never throw — including on nonsense input.
     * Field-level validation belongs on the form, not here.
     *
     * @param  array<string, mixed>  $data
     */
    public function submissionHasContent(array $data): bool
    {
        $data = VisitNotesFormSchema::normalizeSubmission($data);

        if (filled($data['condition_id'] ?? null) || filled($data['diagnosis_free_text'] ?? null)) {
            return true;
        }

        foreach (['advice', 'tests_advised', 'reports_seen', 'clinical_notes', 'chief_complaint', 'history', 'on_examination', 'voice_path', 'voice_transcript', 'follow_up_note'] as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        if (($data['weight_kg'] ?? null) !== null
            || ($data['bp_systolic'] ?? null) !== null
            || ($data['bp_diastolic'] ?? null) !== null
            || ($data['pulse_bpm'] ?? null) !== null
            || ($data['spo2_percent'] ?? null) !== null
            || ($data['temperature_f'] ?? null) !== null) {
            return true;
        }

        if ($this->normalizeUploadedPath($data['prescription_photo'] ?? null) !== null) {
            return true;
        }

        if ($this->normalizeReportPhotoPaths($data['report_photos'] ?? null) !== null) {
            return true;
        }

        if (filled($data['follow_up_date'] ?? null)) {
            return true;
        }

        $items = $data['prescription_items'] ?? [];

        return is_array($items) && collect($items)->contains(
            fn ($item) => filled($item['medicine_name'] ?? null)
        );
    }

    /**
     * Completed bookings today that still have no clinical notes (honest catch-up list).
     */
    public function completedBookingsWithoutNotesToday(?LiveSession $session = null): Collection
    {
        $query = Booking::query()
            ->where('booking_date', today()->toDateString())
            ->where('status', 'completed')
            ->with(['patient', 'visitRecord'])
            ->orderBy('serial_number');

        if ($session) {
            $query->where('bookable_type', ScheduleSession::class)
                ->where('bookable_id', $session->schedule_session_id);
        }

        return $query->get()->filter(function (Booking $booking): bool {
            $record = $booking->visitRecord;

            return ! $record || ! $record->hasClinicalContent();
        })->values();
    }

    public function countCompletedBookingsWithoutNotesToday(?LiveSession $session = null): int
    {
        return $this->completedBookingsWithoutNotesToday($session)->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{coded: bool, condition_id: ?string, name: ?string}
     */
    private function resolveDiagnosis(array $data): array
    {
        $conditionId = $data['condition_id'] ?? null;
        $freeText = $data['diagnosis_free_text'] ?? null;

        if (blank($conditionId) && blank($freeText)) {
            return ['coded' => false, 'condition_id' => null, 'name' => null];
        }

        $resolved = $this->conditionService->resolveSelection($conditionId, $freeText);

        return [
            'coded' => $resolved['coded'],
            'condition_id' => $resolved['condition_id'],
            'name' => $resolved['name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPrescription(VisitRecord $visitRecord, User $doctor, array $data): void
    {
        $items = collect($data['prescription_items'] ?? [])
            ->filter(fn ($item) => filled($item['medicine_name'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            $visitRecord->prescription?->delete();

            return;
        }

        $prescription = Prescription::query()->updateOrCreate(
            ['visit_record_id' => $visitRecord->id],
            [
                'tenant_id' => tenant('id'),
                'patient_id' => $visitRecord->patient_id,
                'prescribed_by' => $doctor->id,
                'advice' => $this->nullableString($data['prescription_advice'] ?? $data['advice'] ?? null),
                'follow_up_date' => $data['follow_up_date'] ?? null,
            ]
        );

        $prescription->items()->delete();

        foreach ($items as $index => $item) {
            PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'medicine_name' => $this->medicineService->normalizeMedicineName((string) $item['medicine_name']),
                'generic_name' => $this->nullableString($item['generic_name'] ?? null),
                'indication' => $this->nullableString($item['indication'] ?? null),
                'dose' => $this->nullableString($item['dose'] ?? null),
                'frequency' => $this->nullableString($item['frequency'] ?? null),
                'duration' => $this->nullableString($item['duration'] ?? null),
                'timing' => \App\Support\PrescriptionTiming::normalize(
                    is_string($item['timing'] ?? null) ? $item['timing'] : null
                ),
                'instructions' => $this->nullableString($item['instructions'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeUploadedPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $this->nullableString(is_string($value) ? $value : null);
    }

    /**
     * @return list<string>|null
     */
    private function normalizeReportPhotoPaths(mixed $value): ?array
    {
        if (! is_array($value)) {
            $value = filled($value) ? [$value] : [];
        }

        $paths = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item[0] ?? null;
            }

            $path = $this->nullableString(is_string($item) ? $item : null);

            if ($path && $this->visitMediaService->isOwnedReportPhotoPath($path)) {
                $paths[] = $path;
            }

            if (count($paths) >= VisitMediaService::REPORT_PHOTO_MAX_FILES) {
                break;
            }
        }

        $paths = array_values(array_unique($paths));

        return $paths === [] ? null : $paths;
    }

    /**
     * @param  list<string>|null  $previous
     * @param  list<string>|null  $next
     */
    private function syncReportPhotos(?array $previous, ?array $next): void
    {
        foreach (array_diff($previous ?? [], $next ?? []) as $removed) {
            $this->visitMediaService->deleteIfExists($removed);
        }
    }
}
