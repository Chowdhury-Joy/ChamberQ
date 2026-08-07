<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Populates the solo tenant admin with realistic operational demo data:
 * patients, today's live queue, visit notes, and past appointment history.
 */
class SoloDemoSeeder extends Seeder
{
    /** Demo patients use 01712345001–01712345010 so re-seeding can replace them safely. */
    private const DEMO_PHONE_PREFIX = '01712345';

    public function run(): void
    {
        $tenant = Tenant::find('solo');

        if (! $tenant || ! $tenant->isSoloDoctor()) {
            return;
        }

        tenancy()->initialize($tenant);

        $this->clearPreviousDemoData();

        $doctor = Doctor::where('name', 'Dr. Shamim Ahmed')->first();

        if (! $doctor) {
            tenancy()->end();

            return;
        }

        $todayDow = Carbon::today()->dayOfWeek;
        $session = ScheduleSession::where('day_of_week', $todayDow)->first()
            ?? ScheduleSession::query()
                ->where('session_name', 'Morning')
                ->orderBy('day_of_week')
                ->first();

        if (! $session) {
            tenancy()->end();

            return;
        }

        $doctorUser = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', 'solo')
            ->where('email', 'doctor@solo.com')
            ->first();

        $patients = $this->seedPatients();
        $today = Carbon::today()->toDateString();

        $bookings = [
            ['patient' => $patients[0], 'serial' => 1, 'status' => 'completed', 'with_notes' => true],
            ['patient' => $patients[1], 'serial' => 2, 'status' => 'completed', 'with_notes' => true],
            ['patient' => $patients[2], 'serial' => 3, 'status' => 'completed', 'with_notes' => false],
            ['patient' => $patients[3], 'serial' => 4, 'status' => 'in_chamber', 'with_notes' => false],
            ['patient' => $patients[4], 'serial' => 5, 'status' => 'waiting', 'with_notes' => false],
            ['patient' => $patients[5], 'serial' => 6, 'status' => 'waiting', 'with_notes' => false],
            ['patient' => $patients[6], 'serial' => 7, 'status' => 'waiting', 'with_notes' => false],
            ['patient' => $patients[7], 'serial' => 8, 'status' => 'waiting', 'with_notes' => false],
        ];

        $createdBookings = [];
        $inChamberBooking = null;

        foreach ($bookings as $row) {
            $patient = $row['patient'];
            $status = $row['status'];
            $now = now();

            $booking = Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $session->id,
                'booking_date' => $today,
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
                'serial_number' => $row['serial'],
                'status' => $status,
                'called_at' => in_array($status, ['called', 'in_chamber', 'completed'], true)
                    ? $now->copy()->subMinutes(20 - $row['serial'])
                    : null,
                'in_chamber_at' => in_array($status, ['in_chamber', 'completed'], true)
                    ? $now->copy()->subMinutes(15 - $row['serial'])
                    : null,
                'completed_at' => $status === 'completed'
                    ? $now->copy()->subMinutes(10 - $row['serial'])
                    : null,
            ]);

            $createdBookings[] = $booking;

            if ($status === 'in_chamber') {
                $inChamberBooking = $booking;
            }

            if ($status === 'completed' && $row['with_notes'] && $doctorUser) {
                VisitRecord::create([
                    'booking_id' => $booking->id,
                    'patient_id' => $patient->id,
                    'recorded_by' => $doctorUser->id,
                    'diagnosis_uncoded' => match ($row['serial']) {
                        1 => 'Type 2 diabetes — stable on current regimen',
                        2 => 'Essential hypertension — well controlled',
                        default => 'General medicine follow-up',
                    },
                    'advice' => match ($row['serial']) {
                        1 => 'Continue metformin 500 mg twice daily after meals. Walk 30 minutes daily. Repeat HbA1c in 3 months.',
                        2 => 'Continue amlodipine 5 mg once daily. Reduce salt. Home BP diary for one week.',
                        default => 'Follow advice discussed in chamber.',
                    },
                    'tests_advised' => $row['serial'] === 1 ? 'HbA1c, fasting lipid profile' : null,
                    'recorded_at' => $booking->completed_at ?? $now,
                ]);
            }
        }

        if ($inChamberBooking) {
            LiveSession::updateOrCreate(
                [
                    'schedule_session_id' => $session->id,
                    'session_date' => $today,
                ],
                [
                    'status' => 'active',
                    'delay_minutes' => 18,
                    'current_booking_id' => $inChamberBooking->id,
                    'current_called_at' => $inChamberBooking->called_at,
                    'started_at' => Carbon::today()->setTime(
                        (int) substr($session->start_time, 0, 2),
                        (int) substr($session->start_time, 3, 2),
                    ),
                ]
            );
        }

        $this->seedPastBookings($patients);

        $tenant->update(['sms_balance' => 47]);

        tenancy()->end();
    }

  private function clearPreviousDemoData(): void
    {
        $phones = collect(range(1, 10))
            ->map(fn (int $i) => self::DEMO_PHONE_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT))
            ->all();

        $bookingIds = Booking::whereIn('patient_phone', $phones)->pluck('id');

        if ($bookingIds->isNotEmpty()) {
            LiveSession::whereIn('current_booking_id', $bookingIds)
                ->update(['current_booking_id' => null]);

            VisitRecord::whereIn('booking_id', $bookingIds)->delete();

            Booking::whereIn('id', $bookingIds)->delete();
        }

        LiveSession::whereDate('session_date', Carbon::today())->delete();

        Patient::whereIn('phone', $phones)->delete();
    }

    /** @return array<int, Patient> */
    private function seedPatients(): array
    {
        $definitions = [
            ['name' => 'Rashida Begum', 'phone' => '01712345001', 'age' => 58, 'sex' => 'female', 'conditions' => 'Type 2 diabetes'],
            ['name' => 'Karim Hossain', 'phone' => '01712345002', 'age' => 52, 'sex' => 'male', 'conditions' => 'Hypertension'],
            ['name' => 'Nasreen Akhtar', 'phone' => '01712345003', 'age' => 45, 'sex' => 'female', 'conditions' => null],
            ['name' => 'Imran Chowdhury', 'phone' => '01712345004', 'age' => 38, 'sex' => 'male', 'allergies' => 'Penicillin'],
            ['name' => 'Farzana Islam', 'phone' => '01712345005', 'age' => 34, 'sex' => 'female', 'conditions' => null],
            ['name' => 'Abdul Malek', 'phone' => '01712345006', 'age' => 61, 'sex' => 'male', 'medicines' => 'Aspirin 75 mg daily'],
            ['name' => 'Sultana Ahmed', 'phone' => '01712345007', 'age' => 49, 'sex' => 'female', 'conditions' => 'Hypothyroidism'],
            ['name' => 'Rafiq Islam', 'phone' => '01712345008', 'age' => 43, 'sex' => 'male', 'conditions' => null],
            ['name' => 'Mina Das', 'phone' => '01712345009', 'age' => 29, 'sex' => 'female', 'conditions' => null],
            ['name' => 'Tariq Hassan', 'phone' => '01712345010', 'age' => 55, 'sex' => 'male', 'conditions' => 'COPD'],
        ];

        $patients = [];

        foreach ($definitions as $row) {
            $patients[] = Patient::create([
                'name' => $row['name'],
                'phone' => $row['phone'],
                'age' => $row['age'],
                'age_recorded_at' => Carbon::today(),
                'sex' => $row['sex'],
                'allergies' => $row['allergies'] ?? null,
                'conditions' => $row['conditions'] ?? null,
                'medicines' => $row['medicines'] ?? null,
            ]);
        }

        return $patients;
    }

    /** @param array<int, Patient> $patients */
    private function seedPastBookings(array $patients): void
    {
        $sessionsByDow = ScheduleSession::all()->keyBy('day_of_week');

        if ($sessionsByDow->isEmpty()) {
            return;
        }

        $serial = 1;
        $patientCount = count($patients);

        for ($daysAgo = 1; $daysAgo <= 30; $daysAgo++) {
            $date = Carbon::today()->subDays($daysAgo);
            $session = $sessionsByDow->get($date->dayOfWeek);

            if (! $session) {
                continue;
            }

            $slotsThisDay = min(4, $patientCount);

            for ($i = 0; $i < $slotsThisDay; $i++) {
                $patient = $patients[($daysAgo + $i) % $patientCount];

                Booking::create([
                    'bookable_type' => ScheduleSession::class,
                    'bookable_id' => $session->id,
                    'booking_date' => $date->toDateString(),
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'patient_phone' => $patient->phone,
                    'serial_number' => $serial,
                    'status' => 'completed',
                    'completed_at' => $date->copy()->setTimeFromTimeString($session->start_time)->addMinutes(15 + ($i * 20)),
                ]);

                $serial++;
            }
        }
    }
}
