<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Services\SmsService;
use App\Support\BookingConfirmationCopy;
use App\Support\GsmText;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS/WhatsApp ticket links are /t/{token}, not /bookings/{uuid}.
 *
 * The UUID page stays as the durable backup (portal, already-opened tabs).
 * The short token lasts until the sitting date plus a week, not 48 hours.
 */
class TicketShortLinkTest extends TestCase
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
            'id' => 'ticket-short',
            'name' => 'Short Ticket Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 10,
            'slot_cap_mode' => 'per_session',
        ]);
        Domain::create(['domain' => 'ticket-short.localhost', 'tenant_id' => 'ticket-short']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Karim']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_cap' => 20,
        ]);
        $this->today = Carbon::today()->toDateString();

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_confirmation_sms_uses_short_ticket_path_not_uuid(): void
    {
        $this->postJson('http://ticket-short.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $booking = Booking::withoutGlobalScopes()->first();
        $this->assertNotNull($booking);
        $this->assertNotNull($booking->ticket_token);
        $this->assertSame(Booking::TICKET_TOKEN_LENGTH, strlen((string) $booking->ticket_token));

        $body = SmsMessage::withoutGlobalScopes()->first()?->body;
        $this->assertNotNull($body);
        $this->assertStringContainsString('/t/'.$booking->ticket_token, $body);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $body);
        $this->assertSame(1, GsmText::segments($body));
    }

    public function test_short_link_opens_the_same_ticket_as_the_uuid_url(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = $this->makeBooking();
        $token = $booking->ticketToken();
        tenancy()->end();

        $this->get('http://ticket-short.localhost/t/'.$token)
            ->assertOk()
            ->assertSee('Fatima', false)
            ->assertSee('Show this number at reception', false);

        $this->get('http://ticket-short.localhost/bookings/'.$booking->id)
            ->assertOk()
            ->assertSee('Fatima', false);
    }

    public function test_short_link_stays_valid_after_the_sitting_day(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = $this->makeBooking();
        $token = $booking->ticketToken();
        tenancy()->end();

        Carbon::setTestNow(Carbon::today()->addDays(Booking::TICKET_LINK_GRACE_DAYS)->setTime(10, 0));

        $this->get('http://ticket-short.localhost/t/'.$token)->assertOk();
    }

    public function test_expired_short_link_404s_uuid_still_works(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = $this->makeBooking();
        $token = $booking->ticketToken();
        tenancy()->end();

        Carbon::setTestNow(
            Carbon::parse($this->today)->endOfDay()->addDays(Booking::TICKET_LINK_GRACE_DAYS)->addSecond()
        );

        $this->get('http://ticket-short.localhost/t/'.$token)->assertNotFound();
        $this->get('http://ticket-short.localhost/bookings/'.$booking->id)->assertOk();
    }

    public function test_whatsapp_copy_uses_the_short_link(): void
    {
        tenancy()->initialize($this->tenant);
        $booking = $this->makeBooking();
        $message = BookingConfirmationCopy::whatsappMessage($booking);
        tenancy()->end();

        $this->assertStringContainsString('/t/'.$booking->ticket_token, $message);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $message);
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get('http://ticket-short.localhost/t/notarealtk')->assertNotFound();
    }

    public function test_ticket_token_columns_exist(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'ticket_token'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'ticket_token_expires_at'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'remarks'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'review_url'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('chambers', 'review_url'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('pharmacy_items', 'chamber_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('pharmacy_sales', 'receipt_number'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('chamber_cash_entries', 'receipt_number'));
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);
    }
}
