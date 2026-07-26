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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blocking a date must cancel what it invalidates and give staff a way to reach
 * those patients. Previously the admin form showed a "Confirm cancellation"
 * checkbox that cancelled nothing, so patients arrived at a closed clinic.
 */
class VacationModeTest extends TestCase
{
    use RefreshDatabase;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $session;

    private LabCollectionSlot $labSlot;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        tenancy()->initialize(Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']));

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr. A']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id, 'doctor_id' => $this->doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 10,
        ]);

        $this->labSlot = LabCollectionSlot::create([
            'chamber_id' => $this->chamber->id, 'day_of_week' => 1,
            'start_time' => '08:00', 'end_time' => '10:00', 'slot_cap' => 10,
        ]);

        $this->monday = Carbon::now()->next(1)->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function book(string $name = 'Patient'): Booking
    {
        return app(BookingService::class)
            ->createBookingForBookable($this->session, $this->monday, $name, '01712345678');
    }

    public function test_blocking_a_chamber_cancels_affected_bookings(): void
    {
        $booking = $this->book();

        SlotBlock::create([
            'chamber_id' => $this->chamber->id,
            'date' => $this->monday,
            'reason' => 'Eid holiday',
        ]);

        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('Eid holiday', $booking->cancellation_reason);
    }

    public function test_a_chamber_block_also_cancels_lab_collection_bookings(): void
    {
        $lab = app(BookingService::class)
            ->createBookingForBookable($this->labSlot, $this->monday, 'Lab Patient', '01712345678');

        SlotBlock::create(['chamber_id' => $this->chamber->id, 'date' => $this->monday]);

        $this->assertSame('cancelled', $lab->refresh()->status);
    }

    public function test_a_doctor_block_leaves_other_doctors_bookings_alone(): void
    {
        $otherDoctor = Doctor::create(['name' => 'Dr. B']);
        $otherSession = ScheduleSession::create([
            'chamber_id' => $this->chamber->id, 'doctor_id' => $otherDoctor->id,
            'day_of_week' => 1, 'session_name' => 'Evening',
            'start_time' => '17:00', 'end_time' => '20:00', 'slot_cap' => 10,
        ]);

        $mine = $this->book('Mine');
        $theirs = app(BookingService::class)
            ->createBookingForBookable($otherSession, $this->monday, 'Theirs', '01712345678');

        SlotBlock::create([
            'doctor_id' => $this->doctor->id,
            'date' => $this->monday,
        ]);

        $this->assertSame('cancelled', $mine->refresh()->status);
        $this->assertSame('waiting', $theirs->refresh()->status, 'Another doctor must be untouched.');
    }

    public function test_bookings_on_other_dates_are_untouched(): void
    {
        $nextWeek = Carbon::parse($this->monday)->addWeek()->format('Y-m-d');
        $later = app(BookingService::class)
            ->createBookingForBookable($this->session, $nextWeek, 'Later', '01712345678');

        SlotBlock::create(['chamber_id' => $this->chamber->id, 'date' => $this->monday]);

        $this->assertSame('waiting', $later->refresh()->status);
    }

    public function test_cancelled_bookings_are_retrievable_for_notification(): void
    {
        $this->book('Alpha');
        $this->book('Beta');

        $block = SlotBlock::create(['chamber_id' => $this->chamber->id, 'date' => $this->monday]);

        $this->assertCount(2, $block->cancelledBookings);
    }

    public function test_whatsapp_link_normalises_a_local_number(): void
    {
        $booking = $this->book();
        $booking->update(['patient_phone' => '01712345678']);

        $link = $booking->fresh()->whatsappLink();

        $this->assertStringStartsWith('https://wa.me/8801712345678?text=', $link);
    }

    public function test_completed_bookings_are_not_cancelled(): void
    {
        $done = $this->book('Done');
        $done->update(['status' => 'completed']);

        SlotBlock::create(['chamber_id' => $this->chamber->id, 'date' => $this->monday]);

        $this->assertSame('completed', $done->refresh()->status);
    }
}
