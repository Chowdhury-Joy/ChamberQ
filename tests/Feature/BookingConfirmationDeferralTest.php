<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\Sms\HttpSmsGateway;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BookingConfirmationDeferralTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'defer-clinic',
            'name' => 'Defer Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 10,
        ]);
        Domain::create(['domain' => 'defer-clinic.localhost', 'tenant_id' => 'defer-clinic']);

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
        ]);

        $this->today = Carbon::today()->toDateString();
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * The serial is committed and the caller is answered before the aggregator
     * is spoken to. Previously the gateway was called inline, so the patient
     * watched a spinner for up to `sms.http.timeout` seconds on a booking that
     * had already succeeded.
     */
    public function test_gateway_is_not_called_before_the_response_is_sent(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->today,
            'Fatima',
            '01712345678',
        );
        tenancy()->end();

        // Booking is durable immediately...
        $this->assertNotNull(Booking::withoutGlobalScopes()->find($booking->id));
        // ...and nothing has been sent yet.
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());

        $this->app->terminate();

        // Once the response is out, the patient is told.
        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
        $this->assertSame(
            SmsMessage::STATUS_SENT,
            SmsMessage::withoutGlobalScopes()->first()->status,
        );
    }

    public function test_it_is_dispatched_after_the_response_not_onto_the_queue(): void
    {
        Bus::fake();

        tenancy()->initialize($this->tenant);
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->today,
            'Fatima',
            '01712345678',
        );
        tenancy()->end();

        // afterResponse, deliberately: no queue worker runs in production, so a
        // queued confirmation would never reach the patient at all.
        Bus::assertDispatchedAfterResponse(
            SendBookingConfirmation::class,
            fn (SendBookingConfirmation $job): bool => $job->bookingId === (string) $booking->id
                && $job->tenantId === 'defer-clinic',
        );
    }

    /**
     * The patient path over HTTP still ends with the patient told — this is the
     * one that proves the deferral did not quietly drop the SMS.
     */
    public function test_booking_over_http_still_results_in_a_sent_confirmation(): void
    {
        $this->postJson('http://defer-clinic.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $message = SmsMessage::withoutGlobalScopes()->first();

        $this->assertNotNull($message);
        $this->assertSame(SmsMessage::STATUS_SENT, $message->status);
        $this->assertSame(9, $this->tenant->fresh()->sms_balance);
    }

    /**
     * Staff can cancel between the wizard's response and the job running.
     * Confirming a serial that no longer exists sends the patient to the
     * chamber for nothing.
     */
    public function test_a_booking_cancelled_before_the_job_runs_is_not_confirmed(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->today,
            'Fatima',
            '01712345678',
        );
        $booking->update(['status' => 'cancelled']);
        tenancy()->end();

        $this->app->terminate();

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
        $this->assertSame(10, $this->tenant->fresh()->sms_balance);
    }

    /**
     * A gateway outage must not surface as a 500 on a booking that worked. The
     * job runs after the response, so an escaping throwable would land in the
     * terminating callbacks rather than anywhere the patient can see.
     */
    public function test_a_throwing_gateway_does_not_break_the_booking(): void
    {
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(string $to, string $message): void
            {
                throw new RuntimeException('gateway down');
            }
        });

        $this->postJson('http://defer-clinic.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        // Credit refunded, failure recorded, booking intact.
        $this->assertSame(10, $this->tenant->fresh()->sms_balance);
        $this->assertSame(
            SmsMessage::STATUS_FAILED,
            SmsMessage::withoutGlobalScopes()->first()->status,
        );
    }

    /**
     * The gateway's error body is stored in `sms_messages.error` and logged.
     * Aggregators commonly echo the request back, so it can carry the account
     * key that authenticates every message this clinic sends.
     */
    public function test_gateway_error_body_does_not_leak_the_api_key(): void
    {
        config([
            'sms.http.url' => 'https://sms.example/send',
            'sms.http.api_key' => 'super-secret-key-12345',
            'sms.http.sender' => 'ChamberQ',
        ]);

        Http::fake([
            'sms.example/*' => Http::response(
                '{"error":"invalid api_key: super-secret-key-12345","sender":"ChamberQ"}',
                401,
            ),
        ]);

        try {
            (new HttpSmsGateway)->send('8801712345678', 'hello');
            $this->fail('Expected the gateway to throw on a non-2xx response.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('super-secret-key-12345', $e->getMessage());
            $this->assertStringContainsString('[redacted]', $e->getMessage());
            // Still diagnosable: status and the shape of the reply survive.
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('invalid api_key', $e->getMessage());
        }
    }

    /**
     * The sign-out diagnostics read their flag from config, so `config:cache`
     * cannot silently disable them the way `env()` did.
     *
     * Deliberately not asserting the *value*: it comes from `AUTH_DEBUG` in
     * whatever `.env` the run picked up, and pinning that would only assert the
     * state of one machine. What must hold is that the key resolves — delete
     * `config/diagnostics.php` and both call sites go permanently null again,
     * which is the failure this whole change exists to remove. The "no `env()`
     * outside `config/`" half is enforced by SourceHygieneTest.
     */
    public function test_auth_diagnostics_flag_is_config_backed(): void
    {
        $this->assertFileExists(config_path('diagnostics.php'));
        $this->assertIsBool(config('diagnostics.auth'));
    }
}
