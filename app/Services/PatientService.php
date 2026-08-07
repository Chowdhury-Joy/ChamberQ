<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
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

    /**
     * Resolve the patient for a new booking — existing id, or find/create by phone + name.
     */
    public function resolveForBooking(string $phone, string $name, ?string $patientId = null): Patient
    {
        $phone = $this->normalizePhone($phone);
        $name = trim($name);

        if ($patientId) {
            $patient = Patient::query()->whereKey($patientId)->where('phone', $phone)->first();

            if ($patient) {
                // Deliberately NOT renamed from the request. The public wizard
                // is only ever shown masked initials for an existing patient,
                // so a submitted name is not that person's real name — writing
                // it back would overwrite "Fatima Rahman" with "F. R.". Staff
                // correct spellings in the Patients resource instead.
                return $patient;
            }
        }

        $normalizedName = $this->normalizeName($name);

        $existing = Patient::query()
            ->where('phone', $phone)
            ->get()
            ->first(fn (Patient $patient) => $this->normalizeName($patient->name) === $normalizedName);

        if ($existing) {
            if ($existing->name !== $name) {
                $existing->update(['name' => $name]);
            }

            return $existing;
        }

        return Patient::create([
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    public function mergePatients(Patient $keep, Patient $remove): Patient
    {
        if ($keep->id === $remove->id) {
            return $keep;
        }

        return DB::transaction(function () use ($keep, $remove) {
            Booking::query()
                ->where('patient_id', $remove->id)
                ->update(['patient_id' => $keep->id]);

            $remove->delete();

            return $keep->fresh();
        });
    }

    public function moveBookingToPatient(Booking $booking, Patient $patient): Booking
    {
        return DB::transaction(function () use ($booking, $patient) {
            $booking->update([
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
            ]);

            return $booking->fresh();
        });
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
