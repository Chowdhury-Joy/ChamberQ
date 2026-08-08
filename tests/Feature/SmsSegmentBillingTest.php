<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Support\GsmText;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Credits are sold as "1 credit = 1 message" and `debitOneCredit()` takes
 * exactly one per send, so an outgoing body must always bill as one segment.
 *
 * This held once before by convention — the templates were written in plain
 * ASCII and a comment asked future authors to keep them that way. It broke
 * within two days, when a feature passed caller-supplied text (carrying a
 * typographic dash) straight through to the gateway: 3 segments billed, 1
 * credit taken. These tests pin the invariant at the send path instead, so it
 * cannot depend on what any caller happens to write.
 */
class SmsSegmentBillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $session;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'seg-clinic',
            'name' => 'Segment Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 20,
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
        ]);
        Domain::create(['domain' => 'seg-clinic.localhost', 'tenant_id' => 'seg-clinic']);

        tenancy()->initialize($this->tenant);

        $this->staff = User::factory()->create([
            'tenant_id' => 'seg-clinic',
            'role' => User::ROLE_STAFF,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create([
            'name' => 'Dr. Segment',
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_CANCELLATION => ['sms' => true, 'whatsapp' => true]],
            ),
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        tenancy()->end();
    }

    /**
     * The exact string LiveQueueControl hands to the notify list after
     * "End session" — the one that regressed. Its typographic dash pushed a
     * 138-character body to three segments.
     */
    public function test_end_session_cancellation_text_bills_one_segment(): void
    {
        $booking = $this->makeBooking('Fatima Rahman', '01712345678');

        $message = __("Hello :name, sorry — today's session ended before your serial :serial. Your appointment has been cancelled. Please contact us to rebook.", [
            'name' => $booking->patient_name,
            'serial' => $booking->serial_number,
        ]);

        $this->assertSame(3, GsmText::segments($message), 'Fixture no longer reproduces the original 3-segment body.');

        $before = $this->tenant->fresh()->sms_balance;

        $this->actingAs($this->staff)
            ->postJson("http://seg-clinic.localhost/api/bookings/{$booking->id}/sms/cancellation", [
                'message' => $message,
            ])
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT);

        $sent = SmsMessage::withoutGlobalScopes()->where('booking_id', $booking->id)->latest('id')->first();

        $this->assertSame(1, GsmText::segments($sent->body));
        $this->assertSame(1, $before - $this->tenant->fresh()->sms_balance);
        $this->assertStringContainsString('sorry - ', $sent->body, 'Dash should be flattened, not dropped.');
    }

    /** Staff free text has no length limit; the send path has to impose one. */
    public function test_overlong_staff_message_bills_one_segment(): void
    {
        $booking = $this->makeBooking('Karim', '01712345679');
        $long = str_repeat('Please contact the clinic to rebook your appointment. ', 12);

        $before = $this->tenant->fresh()->sms_balance;

        $this->actingAs($this->staff)
            ->postJson("http://seg-clinic.localhost/api/bookings/{$booking->id}/sms/cancellation", [
                'message' => $long,
            ])
            ->assertOk();

        $sent = SmsMessage::withoutGlobalScopes()->where('booking_id', $booking->id)->latest('id')->first();

        $this->assertSame(1, GsmText::segments($sent->body));
        $this->assertSame(1, $before - $this->tenant->fresh()->sms_balance);
    }

    /**
     * A patient may type their name in Bangla. Stripping non-Latin would erase
     * them from their own confirmation, so it is transliterated instead.
     */
    public function test_bangla_patient_name_survives_readably_in_one_segment(): void
    {
        $booking = $this->makeBooking('ফাতিমা রহমান', '01712345680');

        $this->actingAs($this->staff)
            ->postJson("http://seg-clinic.localhost/api/bookings/{$booking->id}/sms/cancellation")
            ->assertOk();

        $sent = SmsMessage::withoutGlobalScopes()->where('booking_id', $booking->id)->latest('id')->first();

        $this->assertSame(1, GsmText::segments($sent->body));
        $this->assertStringContainsString('fatima', $sent->body);
        $this->assertSame($sent->body, mb_convert_encoding($sent->body, 'ASCII', 'UTF-8'));
    }

    /** A truncated link is useless; the prose in front of it gives way first. */
    public function test_truncation_keeps_the_link_whole(): void
    {
        $url = 'http://localhost/seg-clinic/bookings/019fdd6b-358f-72e1-88e2-88651a8f883a';
        $body = str_repeat('The doctor is running very late today and we are sorry. ', 6).'Ticket: '.$url;

        $out = GsmText::toSingleSegment($body);

        $this->assertSame(1, GsmText::segments($out));
        $this->assertStringContainsString($url, $out, 'The link must survive truncation intact.');
    }

    /**
     * A link longer than a segment on its own leaves no choice: a bare URL or
     * two segments. A naked link from an unknown number reads as spam, so the
     * context stays and the wallet is charged what was really sent.
     *
     * No message sent today reaches this branch — the fixture below is the
     * signed prescription share URL as it was before /p/{token} replaced it
     * (see PrescriptionShortLinkTest). This pins the fallback for the next long
     * link, and must not be deleted as "unreachable".
     */
    public function test_an_unshortenable_link_keeps_its_context_and_bills_honestly(): void
    {
        $url = 'http://seg-clinic.localhost/prescriptions/019fdf7c-a535-713a-992e-608695a0784b/share'
            .'?expires=1786333715&signature=c91083ef605381509e1c524fc59034ce2a345b26fabe44040a4994255c162e01';

        $this->assertGreaterThan(
            GsmText::SEGMENT_UNITS,
            GsmText::units($url),
            'Fixture no longer reproduces an unshortenable link.'
        );

        $out = GsmText::toSingleSegment('Segment Clinic: Prescription for Laila from 10 Aug 2026 - view: '.$url);

        $this->assertStringContainsString($url, $out, 'The link must stay whole.');
        $this->assertStringContainsString('Prescription for', $out, 'A bare link with no context reads as spam.');
        $this->assertSame(2, GsmText::segments($out));
    }

    /**
     * @dataProvider typographicBodies
     */
    public function test_typographic_characters_are_flattened(string $input, string $expected): void
    {
        $this->assertSame($expected, GsmText::normalize($input));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function typographicBodies(): array
    {
        return [
            'em dash' => ['Fatima — serial 12', 'Fatima - serial 12'],
            'curly apostrophe' => ["today\u{2019}s session", "today's session"],
            'ellipsis' => ["Sending\u{2026}", 'Sending...'],
            'emoji dropped' => ['Done ✅', 'Done'],
            'middle dot collapses spacing' => ['Dr Rahman · Morning', 'Dr Rahman Morning'],
        ];
    }

    /**
     * SMS bodies are built from hardcoded English on purpose. Wrapping one in
     * `__()` would let a future Bangla translation land in a text message,
     * where non-Latin script always costs roughly three times as much — the
     * decision recorded in decisions.md is English-only for SMS.
     */
    public function test_sms_templates_are_not_translatable(): void
    {
        $source = file_get_contents(app_path('Services/SmsService.php'));

        $this->assertSame(
            0,
            preg_match_all('/\b__\(/', $source),
            'SmsService must not use __() — a translated body would triple the segment cost of every send.'
        );
    }

    private function makeBooking(string $name, string $phone): Booking
    {
        tenancy()->initialize($this->tenant);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            $name,
            $phone,
            sendSms: false,
        );

        tenancy()->end();

        return $booking;
    }
}
