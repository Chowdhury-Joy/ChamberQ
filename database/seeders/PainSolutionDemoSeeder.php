<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\FeeCatalogItem;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\ChamberCashService;
use App\Services\StationsTillService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Today's walk-ins with varied Stations till payments for Pain Solution.
 *
 * Run after PainSolutionStationsSeeder:
 * php artisan db:seed --class=PainSolutionDemoSeeder
 */
class PainSolutionDemoSeeder extends Seeder
{
    private const DEMO_PHONE_PREFIX = '01888001';

    public function run(): void
    {
        $tenant = Tenant::find(PainSolutionStationsSeeder::TENANT_ID);

        if (! $tenant || ! $tenant->hasStations()) {
            return;
        }

        tenancy()->initialize($tenant);

        $this->clearPreviousDemoData();

        $todayDow = Carbon::today()->dayOfWeek;
        $visitSession = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->where('kind', ScheduleSession::KIND_VISIT)
            ->first();
        $interventionSession = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->where('kind', ScheduleSession::KIND_INTERVENTION)
            ->first();

        if (! $visitSession || ! $interventionSession) {
            tenancy()->end();

            return;
        }

        $staff = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', PainSolutionStationsSeeder::TENANT_ID)
            ->where('role', User::ROLE_STAFF)
            ->first();

        if (! $staff) {
            tenancy()->end();

            return;
        }

        $visitFee = FeeCatalogItem::query()
            ->where('label', 'Visit (new)')
            ->first();
        $followUpFee = FeeCatalogItem::query()
            ->where('label', 'Follow-up')
            ->first();
        $mskFee = FeeCatalogItem::query()
            ->where('label', 'MSK')
            ->first();

        if (! $visitFee || ! $followUpFee || ! $mskFee) {
            tenancy()->end();

            return;
        }

        $till = app(StationsTillService::class);
        $voucher = app(VoucherService::class);
        $today = Carbon::today()->toDateString();
        $now = now();

        $patients = $this->seedPatients();

        $paidVisitRows = [
            [
                'patient' => $patients[0],
                'serial' => 1,
                'catalog' => $visitFee,
                'cash' => 1000,
                'mobile' => 0,
                'mobile_method' => null,
                'waived' => false,
                'note' => 'Full cash — board price',
            ],
            [
                'patient' => $patients[1],
                'serial' => 2,
                'catalog' => $visitFee,
                'cash' => 0,
                'mobile' => 1000,
                'mobile_method' => ChamberCashEntry::METHOD_BKASH,
                'waived' => false,
                'note' => 'Full bKash — no discount',
            ],
            [
                'patient' => $patients[2],
                'serial' => 3,
                'catalog' => $followUpFee,
                'cash' => 0,
                'mobile' => 800,
                'mobile_method' => ChamberCashEntry::METHOD_NAGAD,
                'waived' => false,
                'note' => 'Follow-up — full Nagad',
            ],
            [
                'patient' => $patients[3],
                'serial' => 4,
                'catalog' => $visitFee,
                'cash' => 400,
                'mobile' => 600,
                'mobile_method' => ChamberCashEntry::METHOD_BKASH,
                'waived' => false,
                'note' => 'Split cash + mobile — no discount',
            ],
            [
                'patient' => $patients[4],
                'serial' => 5,
                'catalog' => $visitFee,
                'cash' => 600,
                'mobile' => 200,
                'mobile_method' => ChamberCashEntry::METHOD_BKASH,
                'waived' => false,
                'note' => '৳200 courtesy discount (collected 800)',
            ],
            [
                'patient' => $patients[5],
                'serial' => 6,
                'catalog' => $visitFee,
                'cash' => 0,
                'mobile' => 0,
                'mobile_method' => null,
                'waived' => true,
                'note' => 'Fee waived — staff relative',
            ],
        ];

        foreach ($paidVisitRows as $row) {
            $booking = $this->createBooking($visitSession, $row['patient'], $today, $row['serial'], 'completed', $now);
            $voucher->assignIfNeeded($booking);
            $till->recordPatientIncome(
                $booking,
                $staff,
                $row['catalog'],
                $row['cash'],
                $row['mobile'],
                $row['mobile_method'],
                $row['note'],
                waived: $row['waived'] ?? false,
            );
        }

        $mskRows = [
            [
                'patient' => $patients[6],
                'serial' => 1,
                'cash' => 3500,
                'mobile' => 0,
                'mobile_method' => null,
                'note' => 'MSK — full cash',
            ],
            [
                'patient' => $patients[7],
                'serial' => 2,
                'cash' => 2500,
                'mobile' => 500,
                'mobile_method' => ChamberCashEntry::METHOD_BKASH,
                'note' => 'MSK — ৳500 discount (collected 3000)',
            ],
        ];

        foreach ($mskRows as $row) {
            $booking = $this->createBooking($interventionSession, $row['patient'], $today, $row['serial'], 'waiting', $now);
            $voucher->assignIfNeeded($booking);
            $till->recordPatientIncome(
                $booking,
                $staff,
                $mskFee,
                $row['cash'],
                $row['mobile'],
                $row['mobile_method'],
                $row['note'],
            );
        }

        // Still at the desk — fee not collected yet (practice Collect fee).
        $this->createBooking($visitSession, $patients[8], $today, 7, 'waiting', $now);
        $this->createBooking($visitSession, $patients[9], $today, 8, 'waiting', $now);

        $this->seedExpenses($staff, $visitSession->chamber_id, Carbon::today());

        tenancy()->end();
    }

    private function seedExpenses(User $staff, int $chamberId, Carbon $day): void
    {
        $cashbook = app(ChamberCashService::class);

        $rows = [
            [
                'amount' => 450,
                'category' => ChamberCashEntry::CATEGORY_SUPPLIES,
                'method' => ChamberCashEntry::METHOD_CASH,
                'note' => '[demo] Tea, biscuits & water for waiting area',
            ],
            [
                'amount' => 1800,
                'category' => ChamberCashEntry::CATEGORY_SUPPLIES,
                'method' => ChamberCashEntry::METHOD_CASH,
                'note' => '[demo] Sterile gloves, syringes, cotton',
            ],
            [
                'amount' => 3200,
                'category' => ChamberCashEntry::CATEGORY_UTILITIES,
                'method' => ChamberCashEntry::METHOD_BKASH,
                'note' => '[demo] DESCO — partial August bill',
            ],
            [
                'amount' => 120,
                'category' => ChamberCashEntry::CATEGORY_TRANSPORT,
                'method' => ChamberCashEntry::METHOD_CASH,
                'note' => '[demo] CNG — staff morning commute',
            ],
            [
                'amount' => 500,
                'category' => ChamberCashEntry::CATEGORY_OTHER_EXPENSE,
                'method' => ChamberCashEntry::METHOD_CASH,
                'note' => '[demo] Chamber cleaning after sitting',
            ],
            [
                'amount' => 15000,
                'category' => ChamberCashEntry::CATEGORY_RENT,
                'method' => ChamberCashEntry::METHOD_BKASH,
                'note' => '[demo] Halishahar room — weekly share',
            ],
        ];

        foreach ($rows as $row) {
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

    private function clearPreviousDemoData(): void
    {
        $phones = collect(range(1, 10))
            ->map(fn (int $i) => self::DEMO_PHONE_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT))
            ->all();

        $bookingIds = Booking::whereIn('patient_phone', $phones)->pluck('id');

        if ($bookingIds->isNotEmpty()) {
            ChamberCashEntry::whereIn('booking_id', $bookingIds)->delete();
            Booking::whereIn('id', $bookingIds)->delete();
        }

        ChamberCashEntry::query()
            ->where('note', 'like', '[demo]%')
            ->delete();

        Patient::whereIn('phone', $phones)->delete();
    }

    /** @return array<int, Patient> */
    private function seedPatients(): array
    {
        $definitions = [
            ['name' => 'Abdul Karim', 'phone' => '01888001001', 'age' => 45, 'sex' => 'male'],
            ['name' => 'Rahim Uddin', 'phone' => '01888001002', 'age' => 52, 'sex' => 'male'],
            ['name' => 'Shahana Begum', 'phone' => '01888001003', 'age' => 38, 'sex' => 'female'],
            ['name' => 'Anwar Hossain', 'phone' => '01888001004', 'age' => 41, 'sex' => 'male'],
            ['name' => 'Fatema Khatun', 'phone' => '01888001005', 'age' => 33, 'sex' => 'female'],
            ['name' => 'Jamal Ahmed', 'phone' => '01888001006', 'age' => 28, 'sex' => 'male'],
            ['name' => 'Hasan Mahmud', 'phone' => '01888001007', 'age' => 55, 'sex' => 'male'],
            ['name' => 'Ripon Das', 'phone' => '01888001008', 'age' => 47, 'sex' => 'male'],
            ['name' => 'Nabil Chowdhury', 'phone' => '01888001009', 'age' => 36, 'sex' => 'male'],
            ['name' => 'Salma Akter', 'phone' => '01888001010', 'age' => 29, 'sex' => 'female'],
        ];

        $patients = [];

        foreach ($definitions as $row) {
            $patients[] = Patient::create([
                'name' => $row['name'],
                'phone' => $row['phone'],
                'age' => $row['age'],
                'age_recorded_at' => Carbon::today(),
                'sex' => $row['sex'],
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
            'called_at' => $status !== 'waiting' ? $now->copy()->subMinutes(30 - $serial) : null,
            'in_chamber_at' => $status === 'completed' ? $now->copy()->subMinutes(20 - $serial) : null,
            'completed_at' => $status === 'completed' ? $now->copy()->subMinutes(10 - $serial) : null,
        ]);
    }
}
