<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private BookingService $bookingService;

    private string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'family-booking']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Family']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->bookingService = app(BookingService::class);
        $this->bookingDate = Carbon::today()->toDateString();
    }

    public function test_two_children_on_one_phone_can_book_same_session_same_day(): void
    {
        $phone = '01711111111';

        $childOne = $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            'Rashed',
            $phone,
        );

        $childTwo = $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            'Fatima',
            $phone,
        );

        $this->assertSame(1, $childOne->serial_number);
        $this->assertSame(2, $childTwo->serial_number);
        $this->assertNotSame($childOne->patient_id, $childTwo->patient_id);
        $this->assertSame(2, Patient::count());
    }

    public function test_same_person_cannot_book_twice_same_day(): void
    {
        $phone = '01722222222';

        $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            'Karim',
            $phone,
        );

        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('already has a booking');

        $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            'Karim',
            $phone,
        );
    }
}
