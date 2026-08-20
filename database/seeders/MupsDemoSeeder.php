<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\PharmacyCount;
use App\Models\PharmacyCountItem;
use App\Models\PharmacyDelivery;
use App\Models\PharmacyDoctorCommission;
use App\Models\PharmacyItem;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Models\PharmacySupplierSettlement;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionOverride;
use App\Models\SlotBlock;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Scopes\TenantScope;
use App\Services\ChamberCashService;
use App\Services\PharmacyStockService;
use App\Support\PrescriptionTiming;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Operational demo data for the MUPS clinic admin (queue, patients, cashbook, labs, pharmacy cupboard).
 *
 * Run after MupsSeeder: php artisan db:seed --class=MupsDemoSeeder
 */
class MupsDemoSeeder extends Seeder
{
    private const DEMO_PHONE_PREFIX = '01899001';

    /**
     * Opening cupboard for the demo till — a few bottles each, not a warehouse.
     *
     * @return array<string, int>
     */
    public static function demoStockByName(): array
    {
        return [
            'Coral D Max' => 12,
            'MH Vitamin' => 10,
            'Joint Pro' => 8,
            'Nervafix' => 6,
            'Vitafix' => 6,
            'Flexactive Extra' => 4,
            'Calcimax' => 10,
            'Neumax' => 8,
            'Slim Herb' => 5,
        ];
    }

    public static function demoStockQty(string $name): int
    {
        return self::demoStockByName()[$name] ?? 0;
    }

    public function run(): void
    {
        $tenant = Tenant::find(MupsSeeder::TENANT_ID);

        if (! $tenant || $tenant->plan_tier !== 'clinic') {
            return;
        }

        tenancy()->initialize($tenant);

        $this->clearPreviousDemoData();

        $doctor = Doctor::query()->orderBy('id')->first();
        $staff = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', MupsSeeder::TENANT_ID)
            ->where('role', User::ROLE_STAFF)
            ->first();
        $doctorUser = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', MupsSeeder::TENANT_ID)
            ->where('role', User::ROLE_DOCTOR)
            ->first();

        $todayDow = Carbon::today()->dayOfWeek;
        $todaysSessions = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->where('kind', ScheduleSession::KIND_VISIT)
            ->orderBy('start_time')
            ->get();

        $primarySession = $todaysSessions->first()
            ?? ScheduleSession::query()
                ->where('kind', ScheduleSession::KIND_VISIT)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->first();

        if (! $doctor || ! $staff || ! $primarySession) {
            tenancy()->end();

            return;
        }

        $this->seedLabs($primarySession->chamber_id);

        $patients = $this->seedPatients();
        $today = Carbon::today()->toDateString();
        $now = now();
        $cash = app(ChamberCashService::class);
        $followUpFee = 'extra:follow-up';
        $cbc = LabTest::query()->where('name', 'CRP (C-reactive protein)')->first();

        $todayRows = [
            ['patient' => $patients[0], 'serial' => 1, 'status' => 'completed', 'notes' => true, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_CASH, 'waived' => false, 'labs' => true],
            ['patient' => $patients[1], 'serial' => 2, 'status' => 'completed', 'notes' => true, 'fee' => $followUpFee, 'method' => ChamberCashEntry::METHOD_BKASH, 'waived' => false, 'labs' => false],
            ['patient' => $patients[2], 'serial' => 3, 'status' => 'completed', 'notes' => true, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_NAGAD, 'waived' => false, 'labs' => false],
            ['patient' => $patients[3], 'serial' => 4, 'status' => 'completed', 'notes' => false, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_CASH, 'waived' => true, 'labs' => false],
            ['patient' => $patients[4], 'serial' => 5, 'status' => 'in_chamber', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false, 'labs' => false],
            ['patient' => $patients[5], 'serial' => 6, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false, 'labs' => false],
            ['patient' => $patients[6], 'serial' => 7, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false, 'labs' => false],
            ['patient' => $patients[7], 'serial' => 8, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false, 'labs' => false],
        ];

        $inChamberBooking = null;

        foreach ($todayRows as $row) {
            $booking = $this->createBooking($primarySession, $row['patient'], $today, $row['serial'], $row['status'], $now);

            if ($row['labs'] && $cbc) {
                $booking->labTests()->attach($cbc->id, [
                    'tenant_id' => $booking->tenant_id,
                    'price_at_booking' => $cbc->price,
                ]);
            }

            if ($row['status'] === 'in_chamber') {
                $inChamberBooking = $booking;
            }

            if ($row['notes'] && $doctorUser) {
                $this->seedVisitAndRx($booking, $row['patient'], $doctorUser, $row['serial']);
            }

            if ($row['method'] !== null) {
                $cash->recordPatientIncome(
                    $booking,
                    $staff,
                    $row['method'],
                    $row['waived'],
                    $row['waived'] ? '[demo] Fee waived — staff relative' : '[demo] Collected at desk',
                    Carbon::today(),
                    $row['fee'] ?? Doctor::FEE_CONSULTATION,
                );
            }

            if ($row['serial'] <= 3) {
                SmsMessage::create([
                    'booking_id' => $booking->id,
                    'to' => $row['patient']->phone,
                    'body' => 'MUPS: serial '.$row['serial'].' confirmed for today. Pay at the chamber.',
                    'purpose' => SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
                    'status' => SmsMessage::STATUS_SENT,
                    'credits' => 1,
                ]);
            }
        }

        $secondSession = $todaysSessions->get(1);
        if ($secondSession) {
            $this->createBooking($secondSession, $patients[8], $today, 1, 'waiting', $now);
            $this->createBooking($secondSession, $patients[9], $today, 2, 'waiting', $now);
        }

        if ($inChamberBooking) {
            LiveSession::updateOrCreate(
                [
                    'schedule_session_id' => $primarySession->id,
                    'session_date' => $today,
                ],
                [
                    'status' => 'active',
                    'delay_minutes' => 15,
                    'current_booking_id' => $inChamberBooking->id,
                    'current_called_at' => $inChamberBooking->called_at,
                    'started_at' => Carbon::today()->setTime(
                        (int) substr($primarySession->start_time, 0, 2),
                        (int) substr($primarySession->start_time, 3, 2),
                    ),
                ]
            );
        }

        $this->seedPastBookings($patients);
        $this->seedFutureBookings($patients);
        $this->seedSlotBlocks($primarySession);
        $this->seedSittingOverride($primarySession);
        $this->seedExpenses($staff, $primarySession->chamber_id);
        $this->seedPharmacyStock($tenant, $staff);

        $tenant->update(['sms_balance' => 42]);

        tenancy()->end();
    }

    public static function wipeTenantOperationalData(): void
    {
        LiveSession::query()->update(['current_booking_id' => null]);
        LiveSession::query()->delete();
        ChamberCashEntry::query()->delete();
        SmsMessage::query()->delete();
        Booking::query()->delete();
        SlotBlock::query()->delete();
        ScheduleSessionOverride::query()->delete();
        LabCollectionSlot::query()->delete();
        LabTest::query()->delete();
        Patient::query()->delete();
    }

    private function seedLabs(int $chamberId): void
    {
        $tests = [
            ['name' => 'MSK ultrasound', 'price' => 2200, 'sample_type' => 'Scan', 'turnaround_time' => 'Same sitting', 'display_order' => 0, 'preparation_instructions' => 'Wear loose clothing. Tell staff if you have had surgery in the area.'],
            ['name' => 'CRP (C-reactive protein)', 'price' => 800, 'sample_type' => 'Blood', 'turnaround_time' => 'Same day', 'display_order' => 1, 'preparation_instructions' => 'No fasting needed.'],
            ['name' => 'ESR', 'price' => 300, 'sample_type' => 'Blood', 'turnaround_time' => 'Same day', 'display_order' => 2, 'preparation_instructions' => 'No special preparation.'],
            ['name' => 'Vitamin D (25-OH)', 'price' => 1800, 'sample_type' => 'Blood', 'turnaround_time' => '48 hours', 'display_order' => 3, 'preparation_instructions' => 'No fasting needed.'],
            ['name' => 'RA factor', 'price' => 900, 'sample_type' => 'Blood', 'turnaround_time' => '24 hours', 'display_order' => 4, 'preparation_instructions' => 'Tell staff if you take steroids.'],
            ['name' => 'Serum uric acid', 'price' => 400, 'sample_type' => 'Blood', 'turnaround_time' => 'Same day', 'display_order' => 5, 'preparation_instructions' => 'Prefer a morning sample.'],
        ];

        foreach ($tests as $test) {
            LabTest::firstOrCreate(['name' => $test['name']], $test + ['is_active' => true]);
        }

        foreach ([0, 2, 4] as $day) {
            LabCollectionSlot::firstOrCreate(
                ['chamber_id' => $chamberId, 'day_of_week' => $day],
                ['start_time' => '08:00', 'end_time' => '11:00', 'slot_cap' => 20],
            );
        }
    }

    /** @return array<int, Patient> */
    private function seedPatients(): array
    {
        $definitions = [
            ['name' => 'Abdul Karim', 'phone' => '01899001001', 'age' => 62, 'sex' => 'male', 'conditions' => 'Lumbar disc herniation', 'medicines' => 'Pregabalin 75 mg at night'],
            ['name' => 'Rahima Begum', 'phone' => '01899001002', 'age' => 55, 'sex' => 'female', 'conditions' => 'Knee osteoarthritis'],
            ['name' => 'Shahidul Islam', 'phone' => '01899001003', 'age' => 47, 'sex' => 'male', 'conditions' => 'Cervical radiculopathy'],
            ['name' => 'Nasrin Akter', 'phone' => '01899001004', 'age' => 38, 'sex' => 'female', 'conditions' => 'Frozen shoulder', 'allergies' => 'NSAIDs'],
            ['name' => 'Faruk Hossain', 'phone' => '01899001005', 'age' => 51, 'sex' => 'male', 'conditions' => 'Sciatica'],
            ['name' => 'Salma Khatun', 'phone' => '01899001006', 'age' => 44, 'sex' => 'female', 'conditions' => 'Plantar fasciitis'],
            ['name' => 'Jamal Uddin', 'phone' => '01899001007', 'age' => 58, 'sex' => 'male', 'conditions' => 'Trigeminal neuralgia'],
            ['name' => 'Taslima Rahman', 'phone' => '01899001008', 'age' => 33, 'sex' => 'female', 'conditions' => 'Myofascial pain'],
            ['name' => 'Rafiq Chowdhury', 'phone' => '01899001009', 'age' => 41, 'sex' => 'male', 'conditions' => 'Ankylosing spondylitis follow-up'],
            ['name' => 'Mina Das', 'phone' => '01899001010', 'age' => 29, 'sex' => 'female', 'conditions' => 'Migraine'],
            ['name' => 'Hasan Mahmud', 'phone' => '01899001011', 'age' => 66, 'sex' => 'male', 'conditions' => 'Post-herpetic neuralgia'],
            ['name' => 'Rina Sultana', 'phone' => '01899001012', 'age' => 36, 'sex' => 'female', 'conditions' => 'SI joint pain'],
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
                'share_clinical_history' => true,
            ]);
        }

        return $patients;
    }

    private function createBooking(
        ScheduleSession $session,
        Patient $patient,
        string $date,
        int $serial,
        string $status,
        Carbon $now,
        bool $wantsEarlier = false,
    ): Booking {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => $date,
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => $serial,
            'status' => $status,
            'wants_earlier_date' => $wantsEarlier,
            'called_at' => in_array($status, ['called', 'in_chamber', 'completed'], true)
                ? $now->copy()->subMinutes(25 - $serial)
                : null,
            'in_chamber_at' => in_array($status, ['in_chamber', 'completed'], true)
                ? $now->copy()->subMinutes(18 - $serial)
                : null,
            'completed_at' => $status === 'completed'
                ? $now->copy()->subMinutes(12 - $serial)
                : null,
        ]);
    }

    private function seedVisitAndRx(Booking $booking, Patient $patient, User $doctorUser, int $serial): void
    {
        $notes = match ($serial) {
            1 => [
                'dx' => 'Lumbar disc herniation L4–L5 — right radiculopathy',
                'cc' => 'Low back pain radiating to the right leg for 6 weeks.',
                'hx' => 'Worse on sitting. No red flags. Tried rest and NSAIDs.',
                'oe' => 'SLR positive at 40° right. Power 5/5. No saddle anaesthesia.',
                'advice' => 'Activity as tolerated. Avoid prolonged sitting. Review after MRI.',
                'tests' => 'MRI lumbosacral spine; CRP',
                'rx' => [
                    ['name' => 'Pregabalin', 'generic' => 'Pregabalin', 'dose' => '75 mg', 'frequency' => '1+0+1', 'duration' => '14 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Naproxen', 'generic' => 'Naproxen', 'dose' => '500 mg', 'frequency' => '1+0+1', 'duration' => '7 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            2 => [
                'dx' => 'Bilateral knee osteoarthritis — follow-up',
                'cc' => 'Knee pain on stairs, better after last injection.',
                'hx' => 'Previous intra-articular steroid 3 months ago.',
                'oe' => 'Crepitus both knees. No effusion today.',
                'advice' => 'Quad strengthening. Weight reduction. Repeat HA if pain returns.',
                'tests' => null,
                'rx' => [
                    ['name' => 'Paracetamol', 'generic' => 'Paracetamol', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '10 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Calcium + Vit D', 'generic' => 'Calcium carbonate', 'dose' => '500 mg', 'frequency' => '0+0+1', 'duration' => '30 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            default => [
                'dx' => 'Cervical radiculopathy C6',
                'cc' => 'Neck pain with tingling in the left thumb.',
                'hx' => 'Desk work 10 hours/day. No trauma.',
                'oe' => 'Spurling positive left. Power intact.',
                'advice' => 'Chin tucks. Limit phone-down posture. Review in 2 weeks.',
                'tests' => 'X-ray cervical spine AP/lat',
                'rx' => [
                    ['name' => 'Gabapentin', 'generic' => 'Gabapentin', 'dose' => '100 mg', 'frequency' => '0+0+1', 'duration' => '14 days', 'timing' => PrescriptionTiming::AT_NIGHT],
                    ['name' => 'Tizanidine', 'generic' => 'Tizanidine', 'dose' => '2 mg', 'frequency' => '0+0+1', 'duration' => '7 days', 'timing' => PrescriptionTiming::AT_NIGHT],
                ],
            ],
        };

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $doctorUser->id,
            'diagnosis_uncoded' => $notes['dx'],
            'chief_complaint' => $notes['cc'],
            'history' => $notes['hx'],
            'on_examination' => $notes['oe'],
            'advice' => $notes['advice'],
            'tests_advised' => $notes['tests'],
            'weight_kg' => 68 + $serial,
            'bp_systolic' => 120 + ($serial * 4),
            'bp_diastolic' => 78,
            'pulse_bpm' => 72,
            'follow_up_date' => Carbon::today()->addWeeks(2),
            'recorded_at' => $booking->completed_at ?? now(),
        ]);

        $rx = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $doctorUser->id,
            'advice' => $notes['advice'],
            'follow_up_date' => Carbon::today()->addWeeks(2),
        ]);

        foreach ($notes['rx'] as $i => $line) {
            PrescriptionItem::create([
                'prescription_id' => $rx->id,
                'medicine_name' => $line['name'],
                'generic_name' => $line['generic'],
                'dose' => $line['dose'],
                'frequency' => $line['frequency'],
                'duration' => $line['duration'],
                'timing' => $line['timing'],
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** @param array<int, Patient> $patients */
    private function seedPastBookings(array $patients): void
    {
        $sessionsByDow = ScheduleSession::query()
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week')
            ->map(fn ($group) => $group->first());

        if ($sessionsByDow->isEmpty()) {
            return;
        }

        $patientCount = count($patients);

        for ($daysAgo = 1; $daysAgo <= 21; $daysAgo++) {
            $date = Carbon::today()->subDays($daysAgo);
            $session = $sessionsByDow->get($date->dayOfWeek);

            if (! $session) {
                continue;
            }

            for ($i = 0; $i < 3; $i++) {
                $patient = $patients[($daysAgo + $i) % $patientCount];

                Booking::create([
                    'bookable_type' => ScheduleSession::class,
                    'bookable_id' => $session->id,
                    'booking_date' => $date->toDateString(),
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'patient_phone' => $patient->phone,
                    'serial_number' => $i + 1,
                    'status' => 'completed',
                    'completed_at' => $date->copy()->setTimeFromTimeString($session->start_time)->addMinutes(20 + ($i * 15)),
                ]);
            }
        }
    }

    /** @param array<int, Patient> $patients */
    private function seedFutureBookings(array $patients): void
    {
        $blocked = [
            Carbon::today()->addDays(8)->toDateString(),
            Carbon::today()->addDays(15)->toDateString(),
        ];

        $sessionsByDow = ScheduleSession::query()
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week')
            ->map(fn ($group) => $group->first());

        $made = 0;
        $now = now();

        for ($daysAhead = 2; $daysAhead <= 20 && $made < 4; $daysAhead++) {
            $date = Carbon::today()->addDays($daysAhead);
            $ymd = $date->toDateString();

            if (in_array($ymd, $blocked, true)) {
                continue;
            }

            $session = $sessionsByDow->get($date->dayOfWeek);

            if (! $session) {
                continue;
            }

            $this->createBooking(
                $session,
                $patients[10 + ($made % 2)],
                $ymd,
                $made + 1,
                'waiting',
                $now,
                wantsEarlier: $made < 2,
            );
            $made++;
        }
    }

    private function seedSlotBlocks(ScheduleSession $session): void
    {
        foreach ([
            ['date' => Carbon::today()->addDays(8)->toDateString(), 'reason' => 'Pain conference — chamber closed'],
            ['date' => Carbon::today()->addDays(15)->toDateString(), 'reason' => 'Public holiday'],
        ] as $block) {
            SlotBlock::updateOrCreate(
                [
                    'date' => $block['date'],
                    'chamber_id' => $session->chamber_id,
                    'doctor_id' => $session->doctor_id,
                ],
                ['reason' => $block['reason']],
            );
        }
    }

    private function seedSittingOverride(ScheduleSession $session): void
    {
        $next = Carbon::today()->next((int) $session->day_of_week);

        if ($next->isToday()) {
            $next->addWeek();
        }

        if (in_array($next->toDateString(), [
            Carbon::today()->addDays(8)->toDateString(),
            Carbon::today()->addDays(15)->toDateString(),
        ], true)) {
            $next->addWeek();
        }

        ScheduleSessionOverride::updateOrCreate(
            [
                'schedule_session_id' => $session->id,
                'override_date' => $next->toDateString(),
            ],
            [
                'start_time' => '18:00',
                'end_time' => $session->end_time,
                'slot_cap' => max(8, (int) $session->slot_cap - 4),
            ],
        );
    }

    private function seedExpenses(User $staff, int $chamberId): void
    {
        $cashbook = app(ChamberCashService::class);
        $day = Carbon::today();

        foreach ([
            ['amount' => 650, 'category' => ChamberCashEntry::CATEGORY_SUPPLIES, 'method' => ChamberCashEntry::METHOD_CASH, 'note' => '[demo] Sterile packs & ultrasound gel'],
            ['amount' => 2200, 'category' => ChamberCashEntry::CATEGORY_UTILITIES, 'method' => ChamberCashEntry::METHOD_BKASH, 'note' => '[demo] Mehedibag electricity share'],
            ['amount' => 180, 'category' => ChamberCashEntry::CATEGORY_TRANSPORT, 'method' => ChamberCashEntry::METHOD_CASH, 'note' => '[demo] CNG — Dhaka sitting'],
            ['amount' => 12000, 'category' => ChamberCashEntry::CATEGORY_RENT, 'method' => ChamberCashEntry::METHOD_BANK, 'note' => '[demo] Uttara room — weekly share'],
        ] as $row) {
            $cashbook->recordExpense(
                $staff,
                $row['amount'],
                $row['category'],
                $row['method'],
                $day,
                $chamberId,
                $row['note'],
            );
        }
    }

    private function seedPharmacyStock(Tenant $tenant, User $staff): void
    {
        if (! $tenant->hasPharmacy()) {
            return;
        }

        $stock = app(PharmacyStockService::class);

        foreach (self::demoStockByName() as $name => $qty) {
            $item = PharmacyItem::query()->where('name', $name)->first();
            if (! $item || $qty < 1) {
                continue;
            }

            $stock->receive(
                $item,
                $staff,
                $qty,
                0,
                true,
                $item->company_share_taka,
                '[demo] Opening cupboard',
            );
        }
    }

    private function resetPharmacyDemoStock(): void
    {
        PharmacyDoctorCommission::query()->delete();
        PharmacySaleItem::query()->delete();
        PharmacySale::query()->delete();
        PharmacyCountItem::query()->delete();
        PharmacyCount::query()->delete();
        PharmacyStockAdjustment::query()->delete();
        PharmacySupplierSettlement::query()->delete();
        PharmacyDelivery::query()->delete();
        PharmacyItem::query()->update(['qty_on_hand' => 0]);
    }

    private function clearPreviousDemoData(): void
    {
        $phones = collect(range(1, 12))
            ->map(fn (int $i) => self::DEMO_PHONE_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT))
            ->all();

        $bookingIds = Booking::whereIn('patient_phone', $phones)->pluck('id');

        if ($bookingIds->isNotEmpty()) {
            LiveSession::whereIn('current_booking_id', $bookingIds)
                ->update(['current_booking_id' => null]);
            ChamberCashEntry::whereIn('booking_id', $bookingIds)->delete();
            SmsMessage::whereIn('booking_id', $bookingIds)->delete();
            Booking::whereIn('id', $bookingIds)->delete();
        }

        LiveSession::where('session_date', Carbon::today()->toDateString())->delete();
        ChamberCashEntry::query()->where('note', 'like', '[demo]%')->delete();
        SlotBlock::query()
            ->where(function ($query): void {
                $query->where('reason', 'like', '%chamber closed')
                    ->orWhere('reason', 'Public holiday');
            })
            ->delete();
        Patient::whereIn('phone', $phones)->delete();
        $this->resetPharmacyDemoStock();
    }
}
