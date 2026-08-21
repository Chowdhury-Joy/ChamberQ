<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'sms-clinic',
            'name' => 'SMS Clinic',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'sms_balance' => 2,
        ]);
        Domain::create(['domain' => 'sms-clinic.localhost', 'tenant_id' => 'sms-clinic']);

        tenancy()->initialize($this->tenant);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. SMS']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        tenancy()->end();
    }

    public function test_booking_debits_wallet_and_logs_sent_sms(): void
    {
        Log::spy();

        $this->postJson('http://sms-clinic.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $this->assertSame(1, $this->tenant->fresh()->sms_balance);
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());

        $message = SmsMessage::withoutGlobalScopes()->first();
        $this->assertNotNull($message);
        $this->assertSame(SmsMessage::STATUS_SENT, $message->status);
        $this->assertSame('8801712345678', $message->to);
        $this->assertSame(1, $message->credits);
        $this->assertStringContainsString('serial 1', $message->body);

        Log::shouldHaveReceived('info')->withArgs(fn ($event) => $event === 'sms.sent');
    }

    public function test_empty_wallet_skips_sms_but_booking_succeeds(): void
    {
        $this->tenant->update(['sms_balance' => 0]);

        $this->postJson('http://sms-clinic.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Rahim',
            'patient_phone' => '01712345679',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(0, $this->tenant->fresh()->sms_balance);
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());

        $message = SmsMessage::withoutGlobalScopes()->first();
        $this->assertSame(SmsMessage::STATUS_SKIPPED_NO_BALANCE, $message->status);
        $this->assertSame(0, $message->credits);
    }

    public function test_failed_gateway_refunds_credit(): void
    {
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(string $to, string $message): void
            {
                throw new \RuntimeException('gateway down');
            }
        });

        tenancy()->initialize($this->tenant);
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Nusrat',
            '01712345680',
        );
        tenancy()->end();

        // The confirmation is handed to SendBookingConfirmation and runs once
        // the response has been sent, so the patient is not made to wait on the
        // gateway. This test drives the service directly rather than over HTTP,
        // so nothing has terminated the request — same as NotifyChannelsTest
        // does for SendDoctorLateNotices.
        $this->app->terminate();

        $this->assertSame(2, $this->tenant->fresh()->sms_balance);

        $message = SmsMessage::withoutGlobalScopes()->where('booking_id', $booking->id)->first();
        $this->assertSame(SmsMessage::STATUS_FAILED, $message->status);
        $this->assertSame(0, $message->credits);
        $this->assertStringContainsString('gateway down', (string) $message->error);
    }

    public function test_confirmation_body_stays_pure_ascii_so_one_credit_is_one_gsm_segment(): void
    {
        // The template's own separators must never force UCS-2 encoding — a
        // non-GSM character (an em dash, a middle dot, …) turns a ~150-char
        // body into 3 SMS segments while debitOneCredit() still takes exactly
        // one, silently under-billing every confirmation sent.
        tenancy()->initialize($this->tenant);
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Fatima',
            '01712345681',
            sendSms: false,
        );
        $body = app(SmsService::class)->confirmationBody($booking);
        tenancy()->end();

        $this->assertSame($body, mb_convert_encoding($body, 'ASCII', 'UTF-8'));
    }

    public function test_top_up_increases_balance(): void
    {
        app(SmsService::class)->topUp($this->tenant, 200);

        $this->assertSame(202, $this->tenant->fresh()->sms_balance);
    }

    public function test_sample_bookings_can_skip_sms(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Sample',
            '01700000001',
            sendSms: false,
        );
        tenancy()->end();

        $this->assertSame(2, $this->tenant->fresh()->sms_balance);
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_path_tenant_ticket_url_uses_app_url_host(): void
    {
        config([
            'app.url' => 'http://localhost',
            'tenancy.central_domains' => ['127.0.0.1', 'localhost'],
        ]);

        $tenant = Tenant::create(['id' => 'path-sms', 'plan_tier' => 'solo']);
        // No Domain row — path-only tenant.
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Path']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 5,
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Path Patient',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $url = app(SmsService::class)->ticketUrl($booking);
        tenancy()->end();

        $this->assertSame('http://localhost/path-sms/t/'.$booking->ticket_token, $url);
        $this->assertStringNotContainsString('127.0.0.1', $url);
    }
}
