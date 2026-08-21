<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionTemplate;
use App\Models\PrescriptionTemplateItem;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionOverride;
use App\Models\SlotBlock;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Scopes\TenantScope;
use App\Services\CashCategoryService;
use App\Services\ChamberCashService;
use App\Services\SmsService;
use App\Support\PrescriptionTiming;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Populates the solo tenant admin with realistic operational demo data:
 * patients, today's live queue, prescriptions, cashbook, SMS log,
 * closed days, waitlist, follow-ups, and past appointment history.
 */
class SoloDemoSeeder extends Seeder
{
    /** Demo patients use 01712345001–01712345014 so re-seeding can replace them safely. */
    private const DEMO_PHONE_PREFIX = '01712345';

    private const DEMO_PATIENT_COUNT = 14;

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
        $morning = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->where('session_name', 'Morning')
            ->first()
            ?? ScheduleSession::query()
                ->where('day_of_week', $todayDow)
                ->orderBy('start_time')
                ->first()
            ?? ScheduleSession::query()
                ->where('session_name', 'Morning')
                ->orderBy('day_of_week')
                ->first();

        if (! $morning) {
            tenancy()->end();

            return;
        }

        $doctorUser = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', 'solo')
            ->where('email', 'doctor@solo.com')
            ->first();

        $adminUser = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', 'solo')
            ->where('email', 'admin@solo.com')
            ->first();

        $cashUser = $adminUser ?? $doctorUser;

        if ($doctorUser && $doctor->user_id === null) {
            $doctor->update(['user_id' => $doctorUser->id]);
        }

        if (! $doctor->default_fee_taka) {
            $doctor->update([
                'default_fee_taka' => 800,
                'extra_fees' => $doctor->extra_fees ?: [
                    ['label' => 'Follow-up', 'amount' => 500],
                    ['label' => 'Review with reports', 'amount' => 600],
                ],
            ]);
        }

        app(CashCategoryService::class)->ensureDefaults();

        $patients = $this->seedPatients();
        $today = Carbon::today()->toDateString();
        $now = now();
        $cash = app(ChamberCashService::class);
        $followUpFee = 'extra:follow-up';

        $todayRows = [
            ['patient' => $patients[0], 'serial' => 1, 'status' => 'completed', 'notes' => true, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_CASH, 'waived' => false],
            ['patient' => $patients[1], 'serial' => 2, 'status' => 'completed', 'notes' => true, 'fee' => $followUpFee, 'method' => ChamberCashEntry::METHOD_BKASH, 'waived' => false],
            ['patient' => $patients[2], 'serial' => 3, 'status' => 'completed', 'notes' => true, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_NAGAD, 'waived' => false],
            ['patient' => $patients[3], 'serial' => 4, 'status' => 'completed', 'notes' => false, 'fee' => Doctor::FEE_CONSULTATION, 'method' => ChamberCashEntry::METHOD_CASH, 'waived' => true],
            ['patient' => $patients[4], 'serial' => 5, 'status' => 'in_chamber', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
            ['patient' => $patients[5], 'serial' => 6, 'status' => 'called', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
            ['patient' => $patients[6], 'serial' => 7, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
            ['patient' => $patients[7], 'serial' => 8, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
            ['patient' => $patients[8], 'serial' => 9, 'status' => 'waiting', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
            ['patient' => $patients[9], 'serial' => 10, 'status' => 'cancelled', 'notes' => false, 'fee' => null, 'method' => null, 'waived' => false],
        ];

        $inChamberBooking = null;

        foreach ($todayRows as $row) {
            $booking = $this->createBooking($morning, $row['patient'], $today, $row['serial'], $row['status'], $now);

            if ($row['status'] === 'in_chamber') {
                $inChamberBooking = $booking;
            }

            if ($row['notes'] && $doctorUser) {
                $this->seedVisitAndRx($booking, $row['patient'], $doctorUser, $row['serial']);
            }

            if ($cashUser && $row['method'] !== null) {
                $cash->recordPatientIncome(
                    $booking,
                    $cashUser,
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
                    'body' => app(SmsService::class)->confirmationBody($booking),
                    'purpose' => SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
                    'status' => SmsMessage::STATUS_SENT,
                    'credits' => 1,
                ]);
            }
        }

        $cancelled = Booking::query()
            ->whereDate('booking_date', $today)
            ->where('status', 'cancelled')
            ->first();

        if ($cancelled) {
            SmsMessage::create([
                'booking_id' => $cancelled->id,
                'to' => $cancelled->patient_phone,
                'body' => 'Dr Shamim: your serial for today was cancelled. Please rebook when you can.',
                'purpose' => SmsMessage::PURPOSE_CANCELLATION,
                'status' => SmsMessage::STATUS_SENT,
                'credits' => 1,
            ]);
        }

        SmsMessage::create([
            'booking_id' => null,
            'to' => $patients[10]->phone,
            'body' => 'Dr Shamim: doctor is running about 20 minutes late. Stay nearby.',
            'purpose' => SmsMessage::PURPOSE_DOCTOR_LATE,
            'status' => SmsMessage::STATUS_FAILED,
            'credits' => 1,
            'error' => '[demo] Gateway timeout',
        ]);

        SmsMessage::create([
            'booking_id' => null,
            'to' => $patients[11]->phone,
            'body' => 'Dr Shamim: prescription link was not sent (wallet empty).',
            'purpose' => SmsMessage::PURPOSE_PRESCRIPTION,
            'status' => SmsMessage::STATUS_SKIPPED_NO_BALANCE,
            'credits' => 0,
        ]);

        $evening = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->where('session_name', 'Evening')
            ->first();

        if ($evening) {
            $this->createBooking($evening, $patients[10], $today, 1, 'waiting', $now);
            $this->createBooking($evening, $patients[11], $today, 2, 'waiting', $now);
        }

        if ($inChamberBooking) {
            LiveSession::updateOrCreate(
                [
                    'schedule_session_id' => $morning->id,
                    'session_date' => $today,
                ],
                [
                    'status' => 'active',
                    'delay_minutes' => 18,
                    'current_booking_id' => $inChamberBooking->id,
                    'current_called_at' => $inChamberBooking->called_at,
                    'started_at' => Carbon::today()->setTime(
                        (int) substr($morning->start_time, 0, 2),
                        (int) substr($morning->start_time, 3, 2),
                    ),
                ]
            );
        }

        $this->seedPastBookings($patients, $doctorUser);
        $this->seedFutureBookings($patients);
        $this->seedSlotBlocks($morning);
        $this->seedSittingOverride($morning);

        if ($doctorUser) {
            $this->seedPrescriptionTemplates($doctorUser);
        }

        if ($cashUser) {
            $this->seedExpenses($cashUser, $morning->chamber_id);
        }

        $tenant->update(['sms_balance' => 47]);

        tenancy()->end();
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
            'cancelled_at' => $status === 'cancelled' ? $now->copy()->subMinutes(40) : null,
            'cancellation_reason' => $status === 'cancelled' ? 'Patient asked to cancel — family emergency' : null,
            'called_at' => in_array($status, ['called', 'in_chamber', 'completed'], true)
                ? $now->copy()->subMinutes(20 - $serial)
                : null,
            'in_chamber_at' => in_array($status, ['in_chamber', 'completed'], true)
                ? $now->copy()->subMinutes(15 - $serial)
                : null,
            'completed_at' => $status === 'completed'
                ? $now->copy()->subMinutes(10 - $serial)
                : null,
        ]);
    }

    private function seedVisitAndRx(Booking $booking, Patient $patient, User $doctorUser, int $serial): void
    {
        $notes = match ($serial) {
            1 => [
                'dx' => 'Type 2 diabetes — stable on current regimen',
                'cc' => 'Routine diabetes review. Occasional tingling in both feet.',
                'hx' => 'On metformin 500 mg twice daily. Last HbA1c 7.2% three months ago.',
                'oe' => 'BP 128/78. Feet intact. No ulcers.',
                'advice' => 'Continue metformin 500 mg twice daily after meals. Walk 30 minutes daily. Repeat HbA1c in 3 months.',
                'tests' => 'HbA1c, fasting lipid profile',
                'follow_weeks' => 12,
                'rx' => [
                    ['name' => 'Comet 500', 'generic' => 'Metformin', 'dose' => '500 mg', 'frequency' => '1+0+1', 'duration' => '90 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Napa', 'generic' => 'Paracetamol', 'dose' => '500 mg', 'frequency' => 'SOS', 'duration' => '10 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            2 => [
                'dx' => 'Essential hypertension — well controlled',
                'cc' => 'Follow-up for blood pressure. Home readings 130–140 systolic.',
                'hx' => 'On amlodipine 5 mg. No chest pain. Occasional headache.',
                'oe' => 'BP 134/82 sitting. Pulse 72. Chest clear.',
                'advice' => 'Continue amlodipine 5 mg once daily. Reduce salt. Home BP diary for one week.',
                'tests' => 'Serum creatinine, electrolytes',
                'follow_weeks' => 8,
                'rx' => [
                    ['name' => 'Amdocal 5', 'generic' => 'Amlodipine', 'dose' => '5 mg', 'frequency' => '0+0+1', 'duration' => '60 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            default => [
                'dx' => 'Acute viral fever — likely URTI',
                'cc' => 'Fever and body ache for 3 days.',
                'hx' => 'No shortness of breath. Vaccinated. No diabetes.',
                'oe' => 'Temp 100.4 F. Throat mildly injected. Lungs clear.',
                'advice' => 'Rest, fluids, paracetamol as needed. Return if fever lasts beyond 5 days.',
                'tests' => 'CBC if fever persists',
                'follow_weeks' => 2,
                'rx' => [
                    ['name' => 'Napa', 'generic' => 'Paracetamol', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '5 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Fexo 120', 'generic' => 'Fexofenadine', 'dose' => '120 mg', 'frequency' => '0+0+1', 'duration' => '5 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
        };

        $followUp = Carbon::today()->addWeeks($notes['follow_weeks']);

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
            'weight_kg' => 62 + ($serial * 3),
            'bp_systolic' => 118 + ($serial * 6),
            'bp_diastolic' => 76,
            'pulse_bpm' => 72,
            'follow_up_date' => $followUp,
            'follow_up_note' => $serial === 1 ? 'Bring latest HbA1c' : null,
            'follow_up_reminder_whatsapp_queued_at' => $serial <= 2 ? now()->subHours(2) : null,
            'recorded_at' => $booking->completed_at ?? now(),
        ]);

        $rx = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $doctorUser->id,
            'advice' => $notes['advice'],
            'follow_up_date' => $followUp,
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

        if ($serial === 1) {
            SmsMessage::create([
                'booking_id' => $booking->id,
                'to' => $patient->phone,
                'body' => 'Dr Shamim: your prescription is ready. Open the link sent on WhatsApp.',
                'purpose' => SmsMessage::PURPOSE_PRESCRIPTION,
                'status' => SmsMessage::STATUS_SENT,
                'credits' => 1,
            ]);
        }
    }

    private function seedPrescriptionTemplates(User $doctorUser): void
    {
        $packs = [
            [
                'name' => 'Type 2 diabetes — first visit',
                'advice' => 'Walk 30 minutes daily. Plate method for meals. Recheck sugar if dizzy.',
                'tests_advised' => 'HbA1c, fasting lipid profile, serum creatinine',
                'follow_up_relative' => '3_months',
                'items' => [
                    ['name' => 'Comet 500', 'generic' => 'Metformin', 'dose' => '500 mg', 'frequency' => '1+0+1', 'duration' => '90 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Ecosprin 75', 'generic' => 'Aspirin', 'dose' => '75 mg', 'frequency' => '0+0+1', 'duration' => '90 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            [
                'name' => 'Hypertension — maintenance',
                'advice' => 'Low salt. Home BP diary. Do not skip the evening tablet.',
                'tests_advised' => 'Serum creatinine, electrolytes',
                'follow_up_relative' => '1_month',
                'items' => [
                    ['name' => 'Amdocal 5', 'generic' => 'Amlodipine', 'dose' => '5 mg', 'frequency' => '0+0+1', 'duration' => '60 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
            [
                'name' => 'Viral fever / URTI',
                'advice' => 'Rest and fluids. Return if breathlessness or fever beyond 5 days.',
                'tests_advised' => 'CBC if fever persists',
                'follow_up_relative' => '1_week',
                'items' => [
                    ['name' => 'Napa', 'generic' => 'Paracetamol', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '5 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                    ['name' => 'Fexo 120', 'generic' => 'Fexofenadine', 'dose' => '120 mg', 'frequency' => '0+0+1', 'duration' => '5 days', 'timing' => PrescriptionTiming::AFTER_FOOD],
                ],
            ],
        ];

        foreach ($packs as $pack) {
            $template = PrescriptionTemplate::updateOrCreate(
                [
                    'user_id' => $doctorUser->id,
                    'name' => $pack['name'],
                ],
                [
                    'advice' => $pack['advice'],
                    'tests_advised' => $pack['tests_advised'],
                    'follow_up_relative' => $pack['follow_up_relative'],
                ],
            );

            $template->items()->delete();

            foreach ($pack['items'] as $i => $line) {
                PrescriptionTemplateItem::create([
                    'prescription_template_id' => $template->id,
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
    }

    private function seedExpenses(User $user, int $chamberId): void
    {
        $cashbook = app(ChamberCashService::class);
        $day = Carbon::today();

        foreach ([
            ['amount' => 450, 'category' => ChamberCashEntry::CATEGORY_SUPPLIES, 'method' => ChamberCashEntry::METHOD_CASH, 'note' => '[demo] Glucometer strips & BP cuff batteries'],
            ['amount' => 1800, 'category' => ChamberCashEntry::CATEGORY_UTILITIES, 'method' => ChamberCashEntry::METHOD_BKASH, 'note' => '[demo] Chamber electricity share'],
            ['amount' => 120, 'category' => ChamberCashEntry::CATEGORY_TRANSPORT, 'method' => ChamberCashEntry::METHOD_CASH, 'note' => '[demo] CNG to Belle Vue'],
            ['amount' => 8000, 'category' => ChamberCashEntry::CATEGORY_RENT, 'method' => ChamberCashEntry::METHOD_BANK, 'note' => '[demo] Room 311 — weekly share'],
        ] as $row) {
            $cashbook->recordExpense(
                $user,
                $row['amount'],
                $row['category'],
                $row['method'],
                $day,
                $chamberId,
                $row['note'],
            );
        }

        $cashbook->recordOtherIncome(
            $user,
            500,
            ChamberCashEntry::CATEGORY_OTHER_INCOME,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $chamberId,
            '[demo] Medical certificate fee',
        );
    }

    private function seedSlotBlocks(ScheduleSession $session): void
    {
        foreach ([
            ['date' => Carbon::today()->addDays(8)->toDateString(), 'reason' => 'Medical conference — chamber closed'],
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
                'start_time' => '10:30',
                'end_time' => $session->end_time,
                'slot_cap' => max(8, (int) $session->slot_cap - 3),
            ],
        );
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
                $patients[12 + ($made % 2)],
                $ymd,
                $made + 1,
                'waiting',
                $now,
                wantsEarlier: $made < 2,
            );
            $made++;
        }
    }

    private function clearPreviousDemoData(): void
    {
        $phones = collect(range(1, self::DEMO_PATIENT_COUNT))
            ->map(fn (int $i) => self::DEMO_PHONE_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT))
            ->all();

        $bookingIds = Booking::whereIn('patient_phone', $phones)->pluck('id');

        if ($bookingIds->isNotEmpty()) {
            LiveSession::whereIn('current_booking_id', $bookingIds)
                ->update(['current_booking_id' => null]);

            ChamberCashEntry::whereIn('booking_id', $bookingIds)->delete();
            SmsMessage::whereIn('booking_id', $bookingIds)->delete();
            VisitRecord::whereIn('booking_id', $bookingIds)->delete();
            Booking::whereIn('id', $bookingIds)->delete();
        }

        LiveSession::whereDate('session_date', Carbon::today())->delete();
        ChamberCashEntry::query()->where('note', 'like', '[demo]%')->delete();
        SmsMessage::query()->where('error', 'like', '[demo]%')->delete();
        SmsMessage::query()->where('body', 'like', 'Dr Shamim:%')->delete();
        SlotBlock::query()
            ->where(function ($query): void {
                $query->where('reason', 'like', '%chamber closed')
                    ->orWhere('reason', 'Public holiday');
            })
            ->delete();
        ScheduleSessionOverride::query()->delete();
        PrescriptionTemplate::query()->delete();
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
            ['name' => 'Shireen Kabir', 'phone' => '01712345011', 'age' => 41, 'sex' => 'female', 'conditions' => 'Migraine'],
            ['name' => 'Jamal Uddin', 'phone' => '01712345012', 'age' => 67, 'sex' => 'male', 'conditions' => 'Ischaemic heart disease'],
            ['name' => 'Laila Khatun', 'phone' => '01712345013', 'age' => 36, 'sex' => 'female', 'allergies' => 'Sulfa drugs'],
            ['name' => 'Nayeem Rahman', 'phone' => '01712345014', 'age' => 27, 'sex' => 'male', 'conditions' => null],
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
    private function seedPastBookings(array $patients, ?User $doctorUser): void
    {
        $sessionsByDow = ScheduleSession::all()->groupBy('day_of_week')
            ->map(fn ($group) => $group->sortBy('start_time')->first());

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
                $status = match (true) {
                    $daysAgo === 3 && $i === 3 => 'no_show',
                    $daysAgo === 6 && $i === 2 => 'cancelled',
                    default => 'completed',
                };

                $completedAt = $status === 'completed'
                    ? $date->copy()->setTimeFromTimeString($session->start_time)->addMinutes(15 + ($i * 20))
                    : null;

                $booking = Booking::create([
                    'bookable_type' => ScheduleSession::class,
                    'bookable_id' => $session->id,
                    'booking_date' => $date->toDateString(),
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'patient_phone' => $patient->phone,
                    'serial_number' => $serial,
                    'status' => $status,
                    'cancelled_at' => $status === 'cancelled' ? $date->copy()->setTime(9, 0) : null,
                    'cancellation_reason' => $status === 'cancelled' ? 'Could not travel' : null,
                    'completed_at' => $completedAt,
                ]);

                if ($status === 'completed' && $doctorUser && $daysAgo % 7 === 0 && $i === 0) {
                    VisitRecord::create([
                        'booking_id' => $booking->id,
                        'patient_id' => $patient->id,
                        'recorded_by' => $doctorUser->id,
                        'diagnosis_uncoded' => 'General medicine follow-up',
                        'advice' => 'Continue current medicines. Review if symptoms return.',
                        'recorded_at' => $completedAt,
                    ]);
                }

                $serial++;
            }
        }
    }
}
