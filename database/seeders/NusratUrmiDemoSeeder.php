<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Today's queue + patient history for the nusraturmi tenant (Daily Roster demo).
 *
 * Run after NusratUrmiSeeder: php artisan db:seed --class=NusratUrmiDemoSeeder
 */
class NusratUrmiDemoSeeder extends Seeder
{
    private const DEMO_PHONE_PREFIX = '01798765';

    public function run(): void
    {
        $tenant = Tenant::find(NusratUrmiSeeder::TENANT_ID);

        if (! $tenant || ! $tenant->isSoloDoctor()) {
            return;
        }

        tenancy()->initialize($tenant);

        $this->clearPreviousDemoData();

        $doctor = Doctor::query()->orderBy('id')->first();

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

        $doctorUser = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', NusratUrmiSeeder::TENANT_ID)
            ->where('role', User::ROLE_DOCTOR)
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

            if ($status === 'in_chamber') {
                $inChamberBooking = $booking;
            }

            if ($status === 'completed' && $row['with_notes'] && $doctorUser) {
                VisitRecord::create([
                    'booking_id' => $booking->id,
                    'patient_id' => $patient->id,
                    'recorded_by' => $doctorUser->id,
                    'diagnosis_uncoded' => match ($row['serial']) {
                        1 => 'Acne vulgaris — moderate inflammatory',
                        2 => 'Melasma — bilateral malar pattern',
                        default => 'Dermatology follow-up',
                    },
                    'advice' => match ($row['serial']) {
                        1 => 'Gentle cleanser twice daily. Topical adapalene at night. Sunscreen SPF 50+ every morning. Review in 6 weeks.',
                        2 => 'Strict photoprotection. Topical triple-combination at night. Avoid waxing on face. Repeat visit in 8 weeks.',
                        default => 'Follow advice discussed in chamber.',
                    },
                    'tests_advised' => $row['serial'] === 2 ? 'Thyroid profile if hormonal triggers suspected' : null,
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
                    'delay_minutes' => 12,
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

        $this->seedSlotBlocks($session);

        tenancy()->end();
    }

    private function seedSlotBlocks(ScheduleSession $session): void
    {
        $chamberId = $session->chamber_id;
        $doctorId = $session->doctor_id;

        $blocks = [
            [
                'date' => Carbon::today()->addDays(8)->toDateString(),
                'reason' => 'Medical conference — chamber closed',
            ],
            [
                'date' => Carbon::today()->addDays(15)->toDateString(),
                'reason' => 'Personal leave',
            ],
        ];

        foreach ($blocks as $block) {
            SlotBlock::updateOrCreate(
                [
                    'date' => $block['date'],
                    'chamber_id' => $chamberId,
                    'doctor_id' => $doctorId,
                ],
                ['reason' => $block['reason']],
            );
        }
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

        LiveSession::where('session_date', Carbon::today()->toDateString())->delete();

        Patient::whereIn('phone', $phones)->delete();
    }

    /** @return array<int, Patient> */
    private function seedPatients(): array
    {
        $definitions = [
            ['name' => 'Rina Begum', 'phone' => '01798765001', 'age' => 24, 'sex' => 'female', 'conditions' => 'Acne'],
            ['name' => 'Nasrin Khanam', 'phone' => '01798765002', 'age' => 38, 'sex' => 'female', 'conditions' => 'Melasma'],
            ['name' => 'Sabina Rahman', 'phone' => '01798765003', 'age' => 45, 'sex' => 'female', 'conditions' => 'Anti-ageing consult'],
            ['name' => 'Mehedi Hasan', 'phone' => '01798765004', 'age' => 32, 'sex' => 'male', 'conditions' => 'Eczema'],
            ['name' => 'Tanzila Akter', 'phone' => '01798765005', 'age' => 27, 'sex' => 'female', 'allergies' => 'Sulfa drugs'],
            ['name' => 'Asif Mahmud', 'phone' => '01798765006', 'age' => 29, 'sex' => 'male', 'conditions' => 'Hair loss'],
            ['name' => 'Hasibur Rahman', 'phone' => '01798765007', 'age' => 41, 'sex' => 'male', 'conditions' => 'Psoriasis'],
            ['name' => 'Karim Ahmed', 'phone' => '01798765008', 'age' => 36, 'sex' => 'male', 'conditions' => 'Mole check'],
            ['name' => 'Farhana Yasmin', 'phone' => '01798765009', 'age' => 22, 'sex' => 'female', 'conditions' => null],
            ['name' => 'Shamim Uddin', 'phone' => '01798765010', 'age' => 52, 'sex' => 'male', 'conditions' => 'Vitiligo'],
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
