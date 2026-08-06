<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillPatientsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_counts_without_writing(): void
    {
        $tenant = Tenant::create(['id' => 'backfill-test']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Backfill']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '+8801712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '01712345678',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        $this->artisan('patients:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame(0, Patient::withoutGlobalScopes()->count());
        $this->assertSame(2, Booking::withoutGlobalScopes()->whereNull('patient_id')->count());
    }

    public function test_backfill_links_bookings_to_patients(): void
    {
        $tenant = Tenant::create(['id' => 'backfill-write']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Backfill']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Nusrat',
            'patient_phone' => '01712345679',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        $this->artisan('patients:backfill')->assertExitCode(0);

        $this->assertSame(1, Patient::withoutGlobalScopes()->count());
        $this->assertNotNull($booking->fresh()->patient_id);
    }
}
