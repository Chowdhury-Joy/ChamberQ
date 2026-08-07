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

        $cancelled = app(LiveSessionService::class)->endSession($liveSession);

        // The people just turned away are handed back so staff can be offered
        // a WhatsApp link for each — cancelling silently is the failure here.
        $this->assertEqualsCanonicalizing(
            [$waiting->id, $called->id],
            $cancelled->pluck('id')->all(),
        );
        $this->assertNotContains($inChamber->id, $cancelled->pluck('id')->all());

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

    public function test_tenant_call_announce_mode_helpers(): void
    {
        $tenant = Tenant::create([
            'id' => 'announce-tenant',
            'plan_tier' => 'solo',
            'call_announce_mode' => Tenant::ANNOUNCE_CHIME,
        ]);

        $this->assertTrue($tenant->usesCallChime());
        $this->assertFalse($tenant->usesCallVoice());

        $tenant->update(['call_announce_mode' => Tenant::ANNOUNCE_VOICE]);
        $tenant = $tenant->fresh();
        $this->assertFalse($tenant->usesCallChime());
        $this->assertTrue($tenant->usesCallVoice());

        $tenant->update(['call_announce_mode' => Tenant::ANNOUNCE_CHIME_AND_VOICE]);
        $tenant = $tenant->fresh();
        $this->assertTrue($tenant->usesCallChime());
        $this->assertTrue($tenant->usesCallVoice());
    }

    public function test_outdoor_screen_includes_voice_announce_script(): void
    {
        $tenant = Tenant::create([
            'id' => 'voice-screen',
            'plan_tier' => 'solo',
            'name' => 'Voice Clinic',
            'call_announce_mode' => Tenant::ANNOUNCE_CHIME_AND_VOICE,
            'call_announce_locale' => 'en',
        ]);
        \App\Models\Domain::create(['domain' => 'voice-screen.localhost', 'tenant_id' => 'voice-screen']);

        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Voice']);
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

        tenancy()->end();

        $this->get('http://voice-screen.localhost/screen/'.$schedule->id.'/'.$today)
            ->assertOk()
            ->assertSee('announceCall', escape: false)
            ->assertSee('announceAudio', escape: false)
            ->assertSee('chimeAudio', escape: false)
            ->assertSee('playAnnounceClip', escape: false)
            // The serial is said three times — one pass is missed in a noisy room.
            ->assertSee('ANNOUNCE_REPEATS = 3', escape: false)
            // A newer call must cut a sequence still repeating the old serial.
            ->assertSee('announceSequence', escape: false)
            ->assertDontSee('speechSynthesis', escape: false);
    }

    public function test_outdoor_screen_voice_only_omits_chime_element(): void
    {
        $tenant = Tenant::create([
            'id' => 'voice-only-screen',
            'plan_tier' => 'solo',
            'call_announce_mode' => Tenant::ANNOUNCE_VOICE,
            'call_announce_locale' => 'bn',
        ]);
        \App\Models\Domain::create(['domain' => 'voice-only-screen.localhost', 'tenant_id' => 'voice-only-screen']);

        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Voice']);
        $schedule = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 10,
        ]);
        $today = Carbon::today()->toDateString();

        tenancy()->end();

        $this->get('http://voice-only-screen.localhost/screen/'.$schedule->id.'/'.$today)
            ->assertOk()
            ->assertSee('playAnnounceClip', escape: false)
            ->assertSee('announceAudio', escape: false)
            ->assertDontSee('id="chimeAudio"', escape: false);
    }

    public function test_live_session_bookings_eager_load_matches_session_date(): void
    {
        $tenant = Tenant::create(['id' => 'eager-session', 'plan_tier' => 'solo']);
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
        $lastWeek = Carbon::today()->subWeek()->toDateString();

        $todayBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $today,
            'patient_name' => 'Today Patient',
            'patient_phone' => '01711111111',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $lastWeekBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $schedule->id,
            'booking_date' => $lastWeek,
            'patient_name' => 'Last Week Patient',
            'patient_phone' => '01722222222',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        LiveSession::create([
            'schedule_session_id' => $schedule->id,
            'session_date' => $today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        LiveSession::create([
            'schedule_session_id' => $schedule->id,
            'session_date' => $lastWeek,
            'status' => 'completed',
            'started_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
        ]);

        $sessions = LiveSession::with('bookings')->orderBy('session_date')->get();

        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions[0]->bookings->contains('id', $lastWeekBooking->id));
        $this->assertFalse($sessions[0]->bookings->contains('id', $todayBooking->id));
        $this->assertTrue($sessions[1]->bookings->contains('id', $todayBooking->id));
        $this->assertFalse($sessions[1]->bookings->contains('id', $lastWeekBooking->id));

        tenancy()->end();
    }
}
