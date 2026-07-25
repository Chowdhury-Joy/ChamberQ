<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use App\Exceptions\BookingUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create(['id' => 'test-tenant']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main Chamber']);
        $this->doctor = Doctor::create(['name' => 'Dr. Smith']);
        
        // Let's create a session on Monday
        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => 1, // Monday
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 2,
        ]);
        
        $this->bookingService = new BookingService();
        $this->mondayDate = Carbon::now()->next(1)->format('Y-m-d');
    }

    public function test_booking_creates_successfully_on_valid_date()
    {
        $booking = $this->bookingService->createBookingForSession(
            $this->session, 
            $this->mondayDate, 
            'John Doe', 
            '01711111111'
        );

        $this->assertEquals(1, $booking->serial_number);
        $this->assertEquals('waiting', $booking->status);
    }

    public function test_booking_fails_on_wrong_day_of_week()
    {
        $tuesdayDate = Carbon::now()->next(2)->format('Y-m-d');
        
        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('not a day this doctor sees patients');
        
        $this->bookingService->createBookingForSession($this->session, $tuesdayDate, 'John Doe', '01711111111');
    }

    public function test_booking_respects_capacity()
    {
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 1', '01711111111');
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 2', '01711111111');
        
        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('fully booked');
        
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 3', '01711111111');
    }

    public function test_max_plus_one_serial_allocation()
    {
        $booking1 = $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 1', '017');
        $this->assertEquals(1, $booking1->serial_number);

        // Cancel first booking to free up capacity, but serial should still increment
        $booking1->update(['status' => 'cancelled']);

        $booking2 = $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 2', '017');
        $this->assertEquals(2, $booking2->serial_number);
    }

    public function test_lab_collection_slots_use_the_same_day_of_week_convention_as_sessions()
    {
        // Regression: lab slots once stored a lowercase day name while sessions
        // stored an integer, so every lab booking was rejected as a day mismatch.
        $slot = LabCollectionSlot::create([
            'chamber_id' => $this->chamber->id,
            'day_of_week' => 1, // Monday, same convention as ScheduleSession
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 5,
        ]);

        $booking = $this->bookingService->createBookingForBookable(
            $slot,
            $this->mondayDate,
            'Lab Patient',
            '01711111111'
        );

        $this->assertEquals(1, $booking->serial_number);
        $this->assertEquals(LabCollectionSlot::class, $booking->bookable_type);
    }

    public function test_lab_booking_rejects_a_date_on_the_wrong_day_of_week()
    {
        $slot = LabCollectionSlot::create([
            'chamber_id' => $this->chamber->id,
            'day_of_week' => 1, // Monday
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 5,
        ]);

        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('not a collection day for this slot');

        $this->bookingService->createBookingForBookable(
            $slot,
            Carbon::parse($this->mondayDate)->addDay()->format('Y-m-d'),
            'Lab Patient',
            '01711111111'
        );
    }

    public function test_slot_blocks_prevent_booking()
    {
        SlotBlock::create([
            'date' => $this->mondayDate,
            'chamber_id' => $this->chamber->id,
            'reason' => 'Holiday',
        ]);

        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('The clinic is closed');
        
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 1', '017');
    }
}
