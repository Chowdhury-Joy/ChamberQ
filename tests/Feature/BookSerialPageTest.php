<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\BookSerial;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BookSerialPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $visit;

    private ScheduleSession $intervention;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create(['id' => 'book-serial', 'plan_tier' => 'clinic']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Karim', 'default_fee_taka' => 800]);

        $this->visit = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'Morning',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 2,
        ]);
        $this->intervention = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 4,
        ]);

        $this->staff = User::create([
            'name' => 'Call centre',
            'email' => 'calls@book-serial.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_staff_can_book_a_future_visit_from_the_admin_page(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $date = '2026-08-22';

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553001',
                'patient_name' => 'Fatima Rahman',
            ])
            ->call('book')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($date, $booking->booking_date->toDateString());
        $this->assertSame(1, $booking->serial_number);
        $this->assertFalse($booking->is_overflow);
        $this->assertSame($this->visit->id, $booking->bookable_id);

        Queue::assertPushed(SendBookingConfirmation::class);
    }

    public function test_phone_booking_uses_the_published_cap_not_walk_in_stools(): void
    {
        $this->visit->update(['slot_cap' => 1, 'walk_in_overflow_cap' => 5]);

        Queue::fake();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $date = '2026-08-22';

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553002',
                'patient_name' => 'First',
            ])
            ->call('book');

        $this->assertSame(1, Booking::query()->count());

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553003',
                'patient_name' => 'Second',
            ])
            ->call('book');

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_call_centre_sitting_list_hides_intervention(): void
    {
        $options = StaffBookingForm::bookableOptions('2026-08-22');

        $this->assertArrayHasKey('session:'.$this->visit->id, $options);
        $this->assertArrayNotHasKey('session:'.$this->intervention->id, $options);
    }
}
