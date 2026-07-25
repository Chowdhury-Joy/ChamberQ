<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Exception;
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
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The requested date does not match the session's configured day of week.");
        
        $this->bookingService->createBookingForSession($this->session, $tuesdayDate, 'John Doe', '01711111111');
    }

    public function test_booking_respects_capacity()
    {
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 1', '01711111111');
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 2', '01711111111');
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Capacity exceeded for the requested date.");
        
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

    public function test_slot_blocks_prevent_booking()
    {
        SlotBlock::create([
            'date' => $this->mondayDate,
            'chamber_id' => $this->chamber->id,
            'reason' => 'Holiday',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The requested date is blocked for this session.");
        
        $this->bookingService->createBookingForSession($this->session, $this->mondayDate, 'Patient 1', '017');
    }
}
