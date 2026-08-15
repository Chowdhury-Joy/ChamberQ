<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OperationalReportService;
use App\Services\RepeatBookingService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class RepeatSerialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-17 10:00', OperationalReportService::TIMEZONE));

        $this->tenant = Tenant::create(['id' => 'repeat-clinic', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'repeat-clinic.localhost', 'tenant_id' => 'repeat-clinic']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create([
            'name' => 'Dr. Repeat',
            'allows_repeat_serials' => true,
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    private function origin(): Booking
    {
        return app(BookingService::class)->createBookingForBookable(
            $this->session,
            '2026-08-17',
            'Fatima Rahman',
            '01710000001',
            sendSms: false,
        );
    }

    public function test_repeat_is_refused_when_admin_has_not_enabled_it(): void
    {
        $this->doctor->update(['allows_repeat_serials' => false]);
        $origin = $this->origin();

        $this->expectException(InvalidArgumentException::class);

        app(RepeatBookingService::class)->repeatFromBooking($origin, 2);
    }

    public function test_repeat_creates_ordinary_future_serials_on_the_same_sitting(): void
    {
        Bus::fake();
        $origin = $this->origin();

        $result = app(RepeatBookingService::class)->repeatFromBooking($origin, 2);

        $this->assertCount(2, $result['created']);
        $this->assertSame(['2026-08-24', '2026-08-31'], array_map(
            fn (Booking $booking): string => $booking->booking_date->toDateString(),
            $result['created'],
        ));
        $this->assertNotEmpty($result['series_id']);
        $this->assertSame($result['series_id'], $origin->fresh()->repeat_series_id);
        foreach ($result['created'] as $booking) {
            $this->assertSame($result['series_id'], $booking->repeat_series_id);
            $this->assertSame('waiting', $booking->status);
            $this->assertFalse($booking->is_overflow);
            $this->assertSame($this->session->id, $booking->bookable_id);
        }

        Bus::assertNotDispatched(SendBookingConfirmation::class);
    }

    public function test_repeat_skips_a_blocked_or_full_date_and_keeps_looking(): void
    {
        $this->session->update(['slot_cap' => 1]);
        $origin = $this->origin();

        SlotBlock::create([
            'date' => '2026-08-24',
            'chamber_id' => $this->chamber->id,
            'reason' => 'Holiday',
        ]);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            '2026-08-31',
            'Someone Else',
            '01710000002',
            sendSms: false,
        );

        $result = app(RepeatBookingService::class)->repeatFromBooking($origin, 2);

        $dates = array_map(
            fn (Booking $booking): string => $booking->booking_date->toDateString(),
            $result['created'],
        );
        $this->assertSame(['2026-09-07', '2026-09-14'], $dates);
        $skipped = array_column($result['skipped'], 'reason', 'date');
        $this->assertSame('blocked', $skipped['2026-08-24']);
        $this->assertSame('full', $skipped['2026-08-31']);
    }

    public function test_cancel_remainder_keeps_this_visit_and_cancels_later_waiting_serials(): void
    {
        $origin = $this->origin();
        $result = app(RepeatBookingService::class)->repeatFromBooking($origin, 2);
        $later = $result['created'][1];

        $cancelled = app(RepeatBookingService::class)->cancelRemainder($origin);

        $this->assertSame(2, $cancelled);
        $this->assertSame('waiting', $origin->fresh()->status);
        $this->assertSame('cancelled', $result['created'][0]->fresh()->status);
        $this->assertSame('cancelled', $later->fresh()->status);
    }

    public function test_roster_repeat_action_is_hidden_until_the_doctor_is_enabled(): void
    {
        $this->doctor->update(['allows_repeat_serials' => false]);
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@repeat-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'repeat-clinic',
        ]);
        $origin = $this->origin();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->assertTableActionHidden('repeatSerial', $origin);
    }

    public function test_roster_repeat_action_books_future_weeks(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff2@repeat-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'repeat-clinic',
        ]);
        $origin = $this->origin();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->callTableAction('repeatSerial', $origin, [
                'weeks' => 2,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(3, Booking::query()->where('patient_phone', '01710000001')->count());
    }
}
