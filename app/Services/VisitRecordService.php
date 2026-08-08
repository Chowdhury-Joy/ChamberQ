<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Condition;
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
            $voiceTranscript = $this->nullableString($data['voice_transcript'] ?? null);

            if ($existing && filled($existing->voice_path) && $voicePath !== $existing->voice_path) {
                $this->visitMediaService->deleteIfExists($existing->voice_path);
            }

            if ($existing && filled($existing->photo_path) && $photoPath !== $existing->photo_path) {
                $this->visitMediaService->deleteIfExists($existing->photo_path);
            }

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
                    'clinical_notes' => $this->nullableString($data['clinical_notes'] ?? null),
                    'advice' => $this->nullableString($data['advice'] ?? null),
                    'tests_advised' => $this->nullableString($data['tests_advised'] ?? null),
                    'reports_seen' => $this->nullableString($data['reports_seen'] ?? null),
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'follow_up_note' => $this->nullableString($data['follow_up_note'] ?? null),
                    'voice_path' => $voicePath,
                    'photo_path' => $photoPath,
                    'voice_transcript' => $voiceTranscript,
                    'recorded_at' => now(),
                ]
            );

            $this->syncPrescription($visitRecord, $doctor, $data);

            if ($booking->status === 'completed') {
                $this->recordUsagesFromSubmission($doctor, $data, $diagnosis);
            }

            return $visitRecord->fresh(['condition', 'prescription.items']);
        });
    }

    /**
     * Staff typing up a prescription the doctor wrote on paper.
     *
     * Writes only the prescription, follow-up and paper photo. Any diagnosis,
     * vitals, clinical notes, advice, tests, reports or voice note already on
     * the record is left untouched — staff never overwrite the doctor's
     * clinical notes, and anything they submit outside the whitelist is discarded.
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

            if ($existing && filled($existing->photo_path) && $photoPath !== $existing->photo_path) {
                $this->visitMediaService->deleteIfExists($existing->photo_path);
            }

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
                    'recorded_at' => now(),
                ]
            );

            $this->syncPrescription($visitRecord, $staff, $data);

            return $visitRecord->fresh(['condition', 'prescription.items']);
        });
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

        foreach (['advice', 'tests_advised', 'reports_seen', 'clinical_notes', 'voice_path', 'voice_transcript', 'follow_up_note'] as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        if (($data['weight_kg'] ?? null) !== null
            || ($data['bp_systolic'] ?? null) !== null
            || ($data['bp_diastolic'] ?? null) !== null) {
            return true;
        }

        if ($this->normalizeUploadedPath($data['prescription_photo'] ?? null) !== null) {
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
                'dose' => $this->nullableString($item['dose'] ?? null),
                'frequency' => $this->nullableString($item['frequency'] ?? null),
                'duration' => $this->nullableString($item['duration'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{coded: bool, condition_id: ?string, name: ?string}  $diagnosis
     */
    private function recordUsagesFromSubmission(User $doctor, array $data, array $diagnosis): void
    {
        if ($diagnosis['coded'] && filled($diagnosis['condition_id'])) {
            $this->conditionService->recordUsage(
                $doctor,
                Condition::query()->findOrFail($diagnosis['condition_id'])
            );
        }

        foreach ($data['prescription_items'] ?? [] as $item) {
            if (blank($item['medicine_name'] ?? null)) {
                continue;
            }

            $this->medicineService->recordUsage($doctor, [
                'medicine_name' => $item['medicine_name'],
                'generic_name' => $item['generic_name'] ?? null,
                'dose' => $item['dose'] ?? null,
                'frequency' => $item['frequency'] ?? null,
                'duration' => $item['duration'] ?? null,
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
}
