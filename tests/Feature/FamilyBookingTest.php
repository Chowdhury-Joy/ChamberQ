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
            'start_time' => '00:00',
            'end_time' => '23:59',
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

    /**
     * The public wizard is only shown masked initials for an existing
     * household member, so it submits an id and no name. The stored record
     * must supply the real name — for the ticket, the SMS, and the roster —
     * and must never be renamed to the mask.
     */
    public function test_booking_an_existing_member_by_id_keeps_their_real_name(): void
    {
        $phone = '01733333333';

        $existing = Patient::create(['name' => 'Fatima Rahman', 'phone' => $phone]);

        $booking = $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            '',            // the wizard has no real name to send
            $phone,
            patientId: $existing->id,
        );

        $this->assertSame($existing->id, $booking->patient_id);
        $this->assertSame('Fatima Rahman', $booking->patient_name);
        $this->assertSame('Fatima Rahman', $existing->fresh()->name);
        $this->assertSame(1, Patient::count(), 'A duplicate patient was created.');
    }

    public function test_a_masked_label_submitted_as_a_name_cannot_rename_the_patient(): void
    {
        $phone = '01744444444';

        $existing = Patient::create(['name' => 'Fatima Rahman', 'phone' => $phone]);

        $booking = $this->bookingService->createBookingForBookable(
            $this->session,
            $this->bookingDate,
            'F. R.',       // what a tampered/legacy client might send back
            $phone,
            patientId: $existing->id,
        );

        $this->assertSame('Fatima Rahman', $existing->fresh()->name);
        $this->assertSame('Fatima Rahman', $booking->patient_name);
    }

    /** The masked label must not carry the name it is meant to hide. */
    public function test_masked_picker_label_exposes_initials_only(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01755555555',
            'age' => 34,
            'age_recorded_at' => now(),
        ]);

        $label = $patient->maskedPickerLabel();

        $this->assertSame('F. R., 34', $label);
        $this->assertStringNotContainsString('Fatima', $label);
        $this->assertStringNotContainsString('Rahman', $label);
    }
}
