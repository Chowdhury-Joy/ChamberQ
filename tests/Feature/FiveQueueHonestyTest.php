<?php

namespace Tests\Feature;

use App\Jobs\SendStaffSittingPromptPushes;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\LiveSessionService;
use App\Services\PublishedComeAround;
use App\Services\SittingPrompt;
use App\Services\SmsService;
use App\Support\GsmText;
use App\Support\ScheduleSessionPace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class FiveQueueHonestyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'five-queue',
            'name' => 'Queue Clinic',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'sms_balance' => 10,
            'eta_model' => Tenant::ETA_SCHEDULE_GUESS,
        ]);
        Domain::create(['domain' => 'five-queue.localhost', 'tenant_id' => 'five-queue']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Karim']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 30,
            'walk_in_overflow_cap' => 5,
        ]);

        $this->today = Carbon::today()->toDateString();
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_published_come_around_for_serial_14_evening(): void
    {
        tenancy()->initialize($this->tenant);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'serial_number' => 14,
            'status' => 'waiting',
        ]);

        $estimate = app(PublishedComeAround::class)->estimateForBooking($booking);

        $this->assertNotNull($estimate);
        $this->assertSame(6, ScheduleSessionPace::minutesPerPatient($this->session));
        $this->assertSame(
            '6:18pm',
            strtolower($estimate['actual_estimate']->format('g:ia')),
        );

        tenancy()->end();
    }

    public function test_booking_sms_includes_come_around_for_live_queue(): void
    {
        $this->postJson('http://five-queue.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $message = SmsMessage::withoutGlobalScopes()->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Come around', $message->body);
        $this->assertStringContainsString('Ticket:', $message->body);
        $this->assertSame(1, GsmText::segments($message->body));
    }

    public function test_public_booking_stops_at_published_cap(): void
    {
        tenancy()->initialize($this->tenant);

        $service = app(BookingService::class);

        for ($i = 1; $i <= 30; $i++) {
            $service->createBookingForBookable(
                $this->session,
                $this->today,
                'Patient '.$i,
                '0171234567'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                sendSms: false,
            );
        }

        $snapshot = $service->availabilityFor($this->session, $this->today);
        $this->assertSame(0, $snapshot['remaining']);

        $this->expectException(\App\Exceptions\BookingUnavailableException::class);
        $service->createBookingForBookable(
            $this->session,
            $this->today,
            'Overflow try',
            '01799999999',
            sendSms: false,
        );

        tenancy()->end();
    }

    public function test_staff_walk_in_can_use_overflow_stools(): void
    {
        tenancy()->initialize($this->tenant);

        $service = app(BookingService::class);

        for ($i = 1; $i <= 30; $i++) {
            $service->createBookingForBookable(
                $this->session,
                $this->today,
                'Patient '.$i,
                '0171234567'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                sendSms: false,
            );
        }

        $overflow = $service->createBookingForBookable(
            $this->session,
            $this->today,
            'Neighbour child',
            '01788888888',
            sendSms: false,
            allowOverflow: true,
        );

        $this->assertSame(31, $overflow->serial_number);
        $this->assertTrue($overflow->is_overflow);

        $body = app(SmsService::class)->confirmationBody($overflow);
        $this->assertStringContainsString('After serial 30', $body);

        tenancy()->end();
    }

    public function test_call_next_skips_overflow_until_published_line_is_done(): void
    {
        tenancy()->initialize($this->tenant);

        $service = app(BookingService::class);
        $liveService = app(LiveSessionService::class);

        $published = $service->createBookingForBookable(
            $this->session,
            $this->today,
            'Published one',
            '01711111111',
            sendSms: false,
        );

        $overflow = $service->createBookingForBookable(
            $this->session,
            $this->today,
            'Stool one',
            '01722222222',
            sendSms: false,
            allowOverflow: true,
        );

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $called = $liveService->callNextPatient($live);

        $this->assertSame($published->id, $called?->id);
        $this->assertSame('called', $published->fresh()->status);
        $this->assertSame('waiting', $overflow->fresh()->status);

        tenancy()->end();
    }

    public function test_call_next_blocked_while_paused(): void
    {
        tenancy()->initialize($this->tenant);

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Waiting',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        app(LiveSessionService::class)->pauseSession($live, 'Prayer', 15);

        $called = app(LiveSessionService::class)->callNextPatient($live->fresh());

        $this->assertNull($called);

        tenancy()->end();
    }

    public function test_idle_after_start_prompt_when_nobody_called(): void
    {
        tenancy()->initialize($this->tenant);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:15:00'));

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => Carbon::parse($this->today.' 17:00:00'),
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Waiting',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $prompt = app(SittingPrompt::class)->promptForSession($this->session, $live);

        $this->assertNotNull($prompt);
        $this->assertSame('idle_after_start', $prompt['kind']);
        $this->assertStringContainsString('Nobody has been called', $prompt['message']);

        tenancy()->end();
    }

    public function test_sitting_prompts_dispatch_staff_buzz_job(): void
    {
        Bus::fake([SendStaffSittingPromptPushes::class]);

        tenancy()->initialize($this->tenant);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:15:00'));

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => Carbon::parse($this->today.' 17:00:00'),
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Waiting',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        app(SittingPrompt::class)->promptsForToday();

        Bus::assertDispatched(SendStaffSittingPromptPushes::class);

        tenancy()->end();
    }

    public function test_walk_in_overflow_columns_exist_on_mysql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL-only schema check.');
        }

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('schedule_sessions', 'walk_in_overflow_cap'),
        );
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'is_overflow'),
        );
    }
}
