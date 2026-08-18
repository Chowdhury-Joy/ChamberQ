<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\MedicineUsage;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Support\PrescriptionTiming;
use Carbon\Carbon;

/**
 * The travel bag a doctor downloads on good internet before a visiting day,
 * and the same snapshot the Rx pad falls back to when the chamber line dies.
 *
 * Not the full catalogue — packs, My medicines, known patients, and letterhead.
 */
class OfflineBagService
{
    public const PATIENT_LIMIT = 150;

    /**
     * @return array<string, mixed>
     */
    public function build(User $doctor): array
    {
        $profile = Doctor::query()->where('user_id', $doctor->id)->first()
            ?? Doctor::query()->orderBy('id')->first();

        $sessions = ScheduleSession::query()
            ->with(['chamber', 'doctor'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ScheduleSession $session): array => [
                'id' => $session->id,
                'label' => trim($session->session_name.' '.($session->start_time ?? '')),
                'chamber_name' => $session->chamber?->name,
                'chamber_address' => $session->chamber?->address,
                'chamber_contact' => $session->chamber?->contact,
                'doctor_name' => $session->doctor?->name ?? $profile?->name,
                'day_of_week' => (int) $session->day_of_week,
            ])
            ->values()
            ->all();

        $today = Carbon::today()->toDateString();

        $todaysBookings = Booking::query()
            ->where('booking_date', $today)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('serial_number')
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'patient_name' => $booking->patient_name,
                'patient_phone' => $booking->patient_phone,
                'serial_number' => $booking->serial_number,
                'status' => $booking->status,
                'bookable_id' => $booking->bookable_id,
                'bookable_type' => $booking->bookable_type,
            ])
            ->values()
            ->all();

        return [
            'packed_at' => now()->toIso8601String(),
            'doctor_id' => $doctor->id,
            'letterhead' => [
                'doctor_name' => $profile?->name ?? $doctor->name,
                'qualifications' => $profile?->qualifications,
                'registration_number' => $profile?->registration_number,
                'chamber_name' => $sessions[0]['chamber_name'] ?? tenant()?->displayName(),
                'chamber_address' => $sessions[0]['chamber_address'] ?? null,
                'chamber_contact' => $sessions[0]['chamber_contact'] ?? null,
            ],
            'timing_labels' => PrescriptionTiming::labels(),
            'packs' => app(PrescriptionTemplateService::class)->forDoctor($doctor),
            'my_medicines' => $this->myMedicines($doctor),
            'sessions' => $sessions,
            'todays_bookings' => $todaysBookings,
            'patients' => $this->knownPatients(),
            'conditions' => $this->conditionPresets(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function myMedicines(User $doctor): array
    {
        return MedicineUsage::query()
            ->where('user_id', $doctor->id)
            ->whereNull('hidden_at')
            ->orderBy('medicine_name')
            ->limit(80)
            ->get()
            ->map(fn (MedicineUsage $usage): array => [
                'medicine_name' => $usage->medicine_name,
                'generic_name' => $usage->generic_name,
                'dose' => $usage->last_dose,
                'frequency' => $usage->last_frequency,
                'duration' => $usage->last_duration,
                'timing' => $usage->last_timing,
                'brand_name' => $usage->medicine_name,
                'label' => $usage->medicine_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function knownPatients(): array
    {
        $rows = Booking::query()
            ->where('status', 'completed')
            ->whereNotNull('patient_id')
            ->with(['patient', 'visitRecord.condition', 'visitRecord.prescription.items'])
            ->orderByDesc('booking_date')
            ->orderByDesc('completed_at')
            ->limit(400)
            ->get();

        $seen = [];
        $out = [];

        foreach ($rows as $booking) {
            $patient = $booking->patient;

            if (! $patient || isset($seen[$patient->id])) {
                continue;
            }

            $seen[$patient->id] = true;
            $visit = $booking->visitRecord;

            $out[] = [
                'id' => $patient->id,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'age' => $patient->displayAge(),
                'year_of_birth' => $patient->yearOfBirth(),
                'sex' => $patient->displaySex(),
                'allergies' => $patient->allergies,
                'conditions' => $patient->conditions,
                'medicines' => $patient->medicines,
                'last_visit' => $visit ? [
                    'date' => optional($booking->booking_date)->toDateString(),
                    'diagnosis' => $visit->diagnosisLabel(),
                    'advice' => $visit->advice,
                    'tests_advised' => $visit->tests_advised,
                    'items' => $visit->prescription?->items
                        ->map(fn ($item): array => [
                            'medicine_name' => $item->medicine_name,
                            'generic_name' => $item->generic_name,
                            'dose' => $item->dose,
                            'frequency' => $item->frequency,
                            'duration' => $item->duration,
                            'timing' => $item->timing,
                            'instructions' => $item->instructions,
                            'indication' => $item->indication,
                        ])
                        ->values()
                        ->all() ?? [],
                ] : null,
            ];

            if (count($out) >= self::PATIENT_LIMIT) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conditionPresets(): array
    {
        return Condition::query()
            ->where(function ($query): void {
                $query->whereNotNull('default_advice')->orWhereNotNull('default_tests');
            })
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->map(fn (Condition $condition): array => [
                'id' => $condition->id,
                'name' => $condition->name,
                'advice' => $condition->adviceForLocale(),
                'tests' => $condition->default_tests,
            ])
            ->values()
            ->all();
    }
}
