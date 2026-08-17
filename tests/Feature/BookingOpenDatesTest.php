<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\WaitingForEarlierDate;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\PlatformSetting;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class BookingOpenDatesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $firstMonday;

    private string $secondMonday;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze on a Monday morning so "today" is a sitting day that has not
        // ended. Carbon::now()->next(MONDAY) skips today when today is Monday,
        // and the API still offers today — the test then asserts the wrong week.
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));

        $this->tenant = Tenant::create(['id' => 'open-dates', 'slot_cap_mode' => 'per_session']);
        Domain::create(['domain' => 'open-dates.localhost', 'tenant_id' => 'open-dates']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Dhanmondi']);
        $doctor = Doctor::create(['name' => 'Dr. Rahman']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 1,
        ]);

        $monday = Carbon::now()->startOfDay();
        if (! $monday->isMonday()) {
            $monday = $monday->next(Carbon::MONDAY);
        }
        $this->firstMonday = $monday->toDateString();
        $this->secondMonday = $monday->copy()->addWeek()->toDateString();

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_open_dates_skips_full_next_sitting_and_returns_later_one(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->firstMonday,
            'Patient One',
            '01711111111',
            sendSms: false,
        );
        tenancy()->end();

        $this->getJson('http://open-dates.localhost/api/bookings/open-dates?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
        ]))
            ->assertOk()
            ->assertJsonPath('has_open_dates', true)
            ->assertJsonPath('options.0.date', $this->secondMonday)
            ->assertJsonPath('options.0.remaining', 1);
    }

    public function test_open_dates_returns_none_when_every_sitting_in_window_is_full(): void
    {
        tenancy()->initialize($this->tenant);
        $service = app(BookingService::class);
        $cursor = Carbon::parse($this->firstMonday);
        $end = Carbon::today()->addDays(60);

        while ($cursor->lte($end)) {
            if ($cursor->dayOfWeek === 1) {
                $suffix = substr((string) $cursor->timestamp, -8);
                $service->createBookingForBookable(
                    $this->session,
                    $cursor->toDateString(),
                    'Patient '.$cursor->toDateString(),
                    '017'.$suffix,
                    sendSms: false,
                );
            }
            $cursor->addDay();
        }
        tenancy()->end();

        $this->getJson('http://open-dates.localhost/api/bookings/open-dates?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
        ]))
            ->assertOk()
            ->assertJsonPath('has_open_dates', false)
            ->assertJsonPath('options', []);
    }

    public function test_open_dates_skips_blocked_dates(): void
    {
        tenancy()->initialize($this->tenant);
        SlotBlock::create([
            'date' => $this->firstMonday,
            'reason' => 'Holiday',
        ]);
        tenancy()->end();

        $this->getJson('http://open-dates.localhost/api/bookings/open-dates?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
        ]))
            ->assertOk()
            ->assertJsonPath('options.0.date', $this->secondMonday);
    }

    public function test_per_doctor_chamber_cap_is_shared_across_sessions(): void
    {
        tenancy()->initialize($this->tenant);
        tenant()->update(['slot_cap_mode' => 'per_doctor_chamber']);

        $evening = ScheduleSession::create([
            'chamber_id' => $this->session->chamber_id,
            'doctor_id' => $this->session->doctor_id,
            'day_of_week' => 1,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 2,
        ]);

        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->firstMonday,
            'Morning patient',
            '01722222222',
            sendSms: false,
        );
        app(BookingService::class)->createBookingForBookable(
            $evening,
            $this->firstMonday,
            'Evening patient',
            '01733333333',
            sendSms: false,
        );
        tenancy()->end();

        $this->getJson('http://open-dates.localhost/api/bookings/open-dates?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id, $evening->id],
        ]))
            ->assertOk()
            ->assertJsonPath('has_open_dates', true)
            ->assertJsonPath('options.0.date', $this->secondMonday);
    }

    public function test_booking_persists_wants_earlier_date_flag(): void
    {
        $this->postJson('http://open-dates.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->firstMonday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'wants_earlier_date' => true,
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->assertTrue(Booking::first()->wants_earlier_date);
        tenancy()->end();
    }

    public function test_booking_persists_optional_whatsapp_phone(): void
    {
        $this->postJson('http://open-dates.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->firstMonday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'whatsapp_phone' => '01812345678',
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $booking = Booking::first();
        $this->assertSame('01712345678', $booking->patient_phone);
        $this->assertSame('01812345678', $booking->whatsapp_phone);
        tenancy()->end();
    }

    public function test_waiting_for_earlier_date_page_lists_future_flagged_bookings(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->secondMonday,
            'Waiting Patient',
            '01744444444',
            sendSms: false,
            wantsEarlierDate: true,
        );
        tenancy()->end();

        $user = \App\Models\User::create([
            'name' => 'Staff',
            'email' => 'staff@open-dates.loc',
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'tenant_id' => 'open-dates',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(WaitingForEarlierDate::class)
            ->assertSee('Waiting Patient')
            ->assertSee('1');
    }

    public function test_public_store_and_availability_honour_the_platform_booking_window(): void
    {
        PlatformSetting::current()->update(['patient_booking_horizon_days' => 30]);

        $tooFar = Carbon::parse($this->firstMonday)->addWeeks(5)->toDateString();

        $this->postJson('http://open-dates.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $tooFar,
            'patient_name' => 'Horizon',
            'patient_phone' => '01712345678',
        ])->assertStatus(422);

        $this->getJson('http://open-dates.localhost/api/bookings/availability?'.http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
            'booking_date' => $tooFar,
        ]))->assertStatus(422);

        PlatformSetting::current()->update(['patient_booking_horizon_days' => 90]);

        $this->postJson('http://open-dates.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $tooFar,
            'patient_name' => 'Horizon',
            'patient_phone' => '01712345678',
        ])->assertOk();
    }
}
