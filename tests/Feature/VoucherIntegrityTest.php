<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoucherIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'voucher-lock',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'voucher-lock.localhost', 'tenant_id' => 'voucher-lock']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Voucher']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_assign_if_needed_is_idempotent(): void
    {
        $booking = $this->booking(1, '01717100001');
        $service = app(VoucherService::class);
        $service->assignIfNeeded($booking);
        $first = $booking->fresh()->voucher_number;
        $service->assignIfNeeded($booking->fresh());

        $this->assertSame($first, $booking->fresh()->voucher_number);
        $this->assertSame(1, $first);
    }

    public function test_patient_ticket_shows_the_voucher_when_stations_is_on(): void
    {
        $booking = $this->booking(1, '01717100008');
        app(VoucherService::class)->assignIfNeeded($booking);
        $number = (string) $booking->fresh()->voucher_number;

        $this->get('http://voucher-lock.localhost/bookings/'.$booking->id)
            ->assertOk()
            ->assertSee('Voucher', false)
            ->assertSee($number, false);
    }

    public function test_free_kinds_do_not_take_a_voucher_number(): void
    {
        $counseling = ScheduleSession::create([
            'chamber_id' => $this->session->chamber_id,
            'doctor_id' => $this->session->doctor_id,
            'day_of_week' => $this->session->day_of_week,
            'session_name' => 'Counseling',
            'kind' => ScheduleSession::KIND_COUNSELING,
            'start_time' => '10:00',
            'end_time' => '14:30',
            'slot_cap' => 40,
        ]);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $counseling->id,
            'booking_date' => today()->toDateString(),
            'patient_name' => 'Free',
            'patient_phone' => '01717100009',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        app(VoucherService::class)->assignIfNeeded($booking);

        $this->assertNull($booking->fresh()->voucher_number);
    }

    public function test_assignment_survives_a_row_created_between_read_and_write(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('lockForUpdate is a no-op on SQLite');
        }

        $today = today()->toDateString();
        $existing = $this->booking(1, '01717100002');
        app(VoucherService::class)->assignIfNeeded($existing);
        $this->assertSame(1, $existing->fresh()->voucher_number);

        $target = $this->booking(2, '01717100003');
        $injected = false;

        Booking::saving(function (Booking $booking) use (&$injected, $today) {
            if ($injected || $booking->voucher_number === null) {
                return;
            }
            $injected = true;

            DB::table('bookings')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => tenant('id'),
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $booking->bookable_id,
                'booking_date' => $today,
                'patient_name' => 'Racer',
                'patient_phone' => '01717100004',
                'serial_number' => 99,
                'voucher_number' => $booking->voucher_number,
                'status' => 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        app(VoucherService::class)->assignIfNeeded($target);

        $numbers = Booking::query()
            ->where('booking_date', $today)
            ->whereNotNull('voucher_number')
            ->pluck('voucher_number');

        $this->assertSame($numbers->count(), $numbers->unique()->count());
        $this->assertNotNull($target->fresh()->voucher_number);
    }

    private function booking(int $serial, string $phone): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_name' => 'Patient '.$serial,
            'patient_phone' => $phone,
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }
}
