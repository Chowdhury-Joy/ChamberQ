<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSessionEndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_session_cancels_remaining_waiting_patients(): void
    {
        $tenant = Tenant::create(['id' => 'end-session', 'plan_tier' => 'solo']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Solo']);
        $schedule = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $today = Carbon::today()->toDateString();

        $completed = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $today,
            'patient_name' => 'Done Patient',
            'patient_phone' => '01711111111',
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $waiting = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $today,
            'patient_name' => 'Waiting Patient',
            'patient_phone' => '01722222222',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);

        $called = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $today,
            'patient_name' => 'Called Patient',
            'patient_phone' => '01733333333',
            'serial_number' => 3,
            'status' => 'called',
            'called_at' => now(),
        ]);

        $inChamber = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $today,
            'patient_name' => 'Fatima In Chamber',
            'patient_phone' => '01744444444',
            'serial_number' => 4,
            'status' => 'in_chamber',
            'in_chamber_at' => now(),
        ]);

        $liveSession = LiveSession::create([
            'schedule_session_id' => $schedule->id,
            'session_date' => $today,
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $inChamber->id,
            'current_called_at' => now(),
        ]);

        app(LiveSessionService::class)->endSession($liveSession);

        $liveSession->refresh();
        $completed->refresh();
        $waiting->refresh();
        $called->refresh();
        $inChamber->refresh();

        $this->assertEquals('completed', $liveSession->status);
        $this->assertNull($liveSession->current_booking_id);
        $this->assertNull($liveSession->current_called_at);
        $this->assertEquals('completed', $completed->status);
        $this->assertEquals('cancelled', $waiting->status);
        $this->assertEquals('Session ended', $waiting->cancellation_reason);
        $this->assertEquals('cancelled', $called->status);
        $this->assertNotNull($called->cancelled_at);
        $this->assertEquals('completed', $inChamber->status);
        $this->assertNotNull($inChamber->completed_at);

        tenancy()->end();
    }

    public function test_tenant_call_audio_url_uses_preset_and_custom_path(): void
    {
        $tenant = Tenant::create([
            'id' => 'audio-tenant',
            'plan_tier' => 'solo',
            'call_audio_preset' => 'soft-bell',
        ]);

        $this->assertEquals('/audio/soft-bell.wav', $tenant->callAudioUrl());

        $tenant->update([
            'call_audio_preset' => 'custom',
            'call_audio_path' => 'call-audio/audio-tenant/custom.wav',
        ]);

        $this->assertEquals('/storage/call-audio/audio-tenant/custom.wav', $tenant->fresh()->callAudioUrl());
    }
}
