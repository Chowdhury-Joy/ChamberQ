<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\VisitRecord;
use App\Support\BdNid;
use App\Support\BdPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientService
{
    public function normalizePhone(string $phone): string
    {
        return BdPhone::normalize($phone);
    }

    public function normalizeName(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    /**
     * @return Collection<int, Patient>
     */
    public function patientsForPhone(string $phone): Collection
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return collect();
        }

        return Patient::query()
            ->where('phone', $normalized)
            ->orderBy('name')
            ->get();
    }

    public function normalizeNid(?string $nid): ?string
    {
        return BdNid::normalize($nid);
    }

    /**
     * Resolve the patient for a new booking — existing id, or find/create.
     *
     * Match order when no household `patient_id`:
     * 1. NID (when provided) — survives a phone change
     * 2. phone + name — household-safe default
     *
     * @param  bool|null  $shareClinicalHistory  When set (online/walk-in checkbox),
     *                                           updates the patient row. Null leaves
     *                                           an existing flag alone; new rows default true.
     * @param  string|null  $nid  Optional BD NID (10 or 13 digits). Blank is fine.
     */
    public function resolveForBooking(
        string $phone,
        string $name,
        ?string $patientId = null,
        ?bool $shareClinicalHistory = null,
        ?string $nid = null,
    ): Patient {
        $phone = $this->normalizePhone($phone);
        $name = trim($name);
        $nid = $this->normalizeNid($nid);

        if ($patientId) {
            $patient = Patient::query()->whereKey($patientId)->where('phone', $phone)->first();

            if ($patient) {
                // Deliberately NOT renamed from the request. The public wizard
                // is only ever shown masked initials for an existing patient,
                // so a submitted name is not that person's real name — writing
                // it back would overwrite "Fatima Rahman" with "F. R.". Staff
                // correct spellings in the Patients resource instead.
                $this->applyBookingUpdates($patient, $phone, $name, $nid, $shareClinicalHistory, rename: false);

                return $patient->fresh() ?? $patient;
            }
        }

        if ($nid !== null) {
            $byNid = Patient::query()->where('nid', $nid)->first();

            if ($byNid) {
                $this->applyBookingUpdates($byNid, $phone, $name, $nid, $shareClinicalHistory, rename: true);

                return $byNid->fresh() ?? $byNid;
            }
        }

        $normalizedName = $this->normalizeName($name);

        $existing = Patient::query()
            ->where('phone', $phone)
            ->get()
            ->first(fn (Patient $patient) => $this->normalizeName($patient->name) === $normalizedName);

        if ($existing) {
            $this->applyBookingUpdates($existing, $phone, $name, $nid, $shareClinicalHistory, rename: true);

            return $existing->fresh() ?? $existing;
        }

        return Patient::create([
            'name' => $name !== '' ? $name : 'Patient',
            'phone' => $phone,
            'nid' => $nid,
            'share_clinical_history' => $shareClinicalHistory ?? true,
        ]);
    }

    /**
     * @param  bool  $rename  When true, a non-empty request name may overwrite the stored name.
     */
    private function applyBookingUpdates(
        Patient $patient,
        string $phone,
        string $name,
        ?string $nid,
        ?bool $shareClinicalHistory,
        bool $rename,
    ): void {
        $updates = [];

        if ($patient->phone !== $phone) {
            $updates['phone'] = $phone;
        }

        if ($rename && $name !== '' && $patient->name !== $name) {
            $updates['name'] = $name;
        }

        if ($nid !== null && $patient->nid !== $nid) {
            $updates['nid'] = $nid;
        }

        if ($shareClinicalHistory !== null
            && (bool) $patient->share_clinical_history !== $shareClinicalHistory) {
            $updates['share_clinical_history'] = $shareClinicalHistory;
        }

        if ($updates !== []) {
            $patient->update($updates);
        }
    }

    /**
     * Fold a duplicate patient record into the one being kept.
     *
     * Every table that points at a patient has to be moved, not just bookings.
     * `visit_records.patient_id` and `prescriptions.patient_id` are
     * `nullOnDelete` foreign keys, so deleting the duplicate without moving
     * them first silently NULLs them — and the consult screen reads history by
     * `patient_id`, so the doctor is then told "no history" for a patient whose
     * allergy note is still in the database with nothing pointing at it. There
     * is no screen that can re-link it afterwards.
     */
    public function mergePatients(Patient $keep, Patient $remove): Patient
    {
        if ($keep->id === $remove->id) {
            return $keep;
        }

        return DB::transaction(function () use ($keep, $remove) {
            $this->repointPatientOwnedRows($remove->id, $keep->id);

            $remove->delete();

            return $keep->fresh();
        });
    }

    /**
     * Move one visit to a different patient (staff correcting a mis-filed booking).
     *
     * The visit record and prescription hang off the booking, so they have to
     * travel with it — otherwise the clinical note stays filed under the wrong
     * person while the appointment moves.
     */
    public function moveBookingToPatient(Booking $booking, Patient $patient): Booking
    {
        return DB::transaction(function () use ($booking, $patient) {
            $booking->update([
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
            ]);

            VisitRecord::query()
                ->where('booking_id', $booking->id)
                ->update(['patient_id' => $patient->id]);

            Prescription::query()
                ->whereIn('visit_record_id', VisitRecord::query()
                    ->where('booking_id', $booking->id)
                    ->select('id'))
                ->update(['patient_id' => $patient->id]);

            return $booking->fresh();
        });
    }

    /**
     * Repoint every patient-owned row from one patient to another.
     *
     * Kept in one place so a table added later has a single obvious home; a
     * merge that misses a table loses clinical history irreversibly.
     */
    private function repointPatientOwnedRows(string $fromPatientId, string $toPatientId): void
    {
        Booking::query()
            ->where('patient_id', $fromPatientId)
            ->update(['patient_id' => $toPatientId]);

        VisitRecord::query()
            ->where('patient_id', $fromPatientId)
            ->update(['patient_id' => $toPatientId]);

        Prescription::query()
            ->where('patient_id', $fromPatientId)
            ->update(['patient_id' => $toPatientId]);
    }

    /**
     * Names on the same phone that look like typos of each other (staff may want to merge).
     *
     * @return list<array{phone: string, name_a: string, name_b: string, similarity: float}>
     */
    public function findSuspiciousNamePairs(Collection $patientsByPhone): array
    {
        $suspicious = [];

        foreach ($patientsByPhone as $phone => $names) {
            $unique = array_values(array_unique($names));
            $count = count($unique);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    similar_text($unique[$i], $unique[$j], $percent);

                    if ($percent >= 70 && $percent < 100) {
                        $suspicious[] = [
                            'phone' => $phone,
                            'name_a' => $unique[$i],
                            'name_b' => $unique[$j],
                            'similarity' => round($percent, 1),
                        ];
                    }
                }
            }
        }

        return $suspicious;
    }
}
