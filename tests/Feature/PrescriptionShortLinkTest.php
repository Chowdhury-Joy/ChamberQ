<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\SmsService;
use App\Support\GsmText;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The short `/p/{token}` share link.
 *
 * Its whole reason to exist is billing: the temporary signed URL it replaced ran
 * ~181 characters — longer than a GSM segment before a single word — so every
 * prescription SMS cost two credits while clinics are sold "1 credit = 1
 * message". The security properties (unguessable, expiring) moved from the query
 * string into the row, so they are pinned here rather than assumed.
 *
 * Deliberately built on a longer-than-real clinic name, patient name and domain:
 * a fixture short enough to pass by accident would prove nothing.
 */
class PrescriptionShortLinkTest extends TestCase
{
    use RefreshDatabase;

    /** A long but ordinary clinic domain. */
    private const LONG_DOMAIN = 'dr-rahman-medical-centre.com';

    /** Longer than any domain a clinic has actually registered. */
    private const EXTREME_DOMAIN = 'appointments.dr-rahman-medical-centre.com.bd';

    private const LONG_CLINIC_NAME = 'Dr Rahman Medical Centre & Diagnostics';

    private const LONG_PATIENT_NAME = 'Mohammad Abdur Rahman Chowdhury';

    private Tenant $tenant;

    private User $staff;

    private Booking $booking;

    private Prescription $prescription;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'dr-rahman-medical-centre-dhaka',
            'name' => self::LONG_CLINIC_NAME,
            'plan_tier' => 'solo',
            'sms_balance' => 20,
        ]);
        Domain::create(['domain' => self::LONG_DOMAIN, 'tenant_id' => $this->tenant->id]);
        Domain::create(['domain' => self::EXTREME_DOMAIN, 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $doctorUser = User::create([
            'name' => 'Dr Rahman',
            'email' => 'doc@rahman.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Front Desk',
            'email' => 'staff@rahman.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main Chamber']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Rahman',
            'registration_number' => 'C-55443',
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_PRESCRIPTION => ['sms' => true, 'whatsapp' => true]],
            ),
        ]);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::create([
            'name' => self::LONG_PATIENT_NAME,
            'phone' => '01712345699',
            'age' => 30,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $visitRecord = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $doctorUser->id,
            'recorded_at' => now(),
        ]);

        $this->prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visitRecord->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $doctorUser->id,
            'advice' => 'Take after meals',
        ]);

        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medicine_name' => 'NAPA',
            'dose' => '500mg',
            'sort_order' => 1,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * The point of the whole change: a long clinic name, a long patient name
     * and a long domain together still fit one segment *whole* — nothing is
     * truncated to get there.
     *
     * The margin is small (a handful of characters). If this starts failing,
     * the fix is to shorten the template, not to shorten the fixture.
     */
    public function test_worst_case_prescription_sms_is_one_untruncated_segment(): void
    {
        $body = $this->onTenantHost(
            fn () => app(SmsService::class)->prescriptionBody($this->booking, $this->prescription)
        );

        $this->assertStringContainsString(self::LONG_CLINIC_NAME, $body);
        $this->assertStringContainsString(self::LONG_PATIENT_NAME, $body);
        $this->assertStringNotContainsString('...', GsmText::toSingleSegment($body));
        $this->assertSame(
            1,
            GsmText::segments($body),
            'Prescription SMS must fit one segment. Body was '.GsmText::units($body)." units:\n".$body
        );
    }

    /** And the wallet is actually debited once, not twice. */
    public function test_sending_the_prescription_sms_costs_one_credit(): void
    {
        $before = $this->tenant->fresh()->sms_balance;

        $this->actingAs($this->staff)
            ->postJson('http://'.self::LONG_DOMAIN.'/api/prescriptions/'.$this->prescription->id.'/sms')
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT)
            ->assertJsonPath('credits', 1);

        $this->assertSame(1, $before - $this->tenant->fresh()->sms_balance);

        $sent = SmsMessage::withoutGlobalScopes()
            ->where('booking_id', $this->booking->id)
            ->latest('id')
            ->first();

        $this->assertSame(1, GsmText::segments($sent->body));
        $this->assertStringContainsString('/p/', $sent->body);
    }

    /**
     * Past the point where it fits, the message must degrade rather than
     * silently overbill: prose gives way, the link survives whole, one credit.
     */
    public function test_an_extremely_long_domain_still_costs_one_credit(): void
    {
        // publicAbsolute uses the first Domain row — leave only the extreme host.
        Domain::where('tenant_id', $this->tenant->id)
            ->where('domain', self::LONG_DOMAIN)
            ->delete();

        $body = $this->onTenantHost(
            fn () => app(SmsService::class)->prescriptionBody($this->booking, $this->prescription),
            self::EXTREME_DOMAIN,
        );

        $link = $this->onTenantHost(
            fn () => $this->prescription->shareUrl(),
            self::EXTREME_DOMAIN,
        );
        $out = GsmText::toSingleSegment($body);

        $this->assertSame(1, GsmText::segments($out));
        $this->assertStringStartsWith('https://'.self::EXTREME_DOMAIN.'/p/', $link);
        $this->assertStringContainsString($link, $out, 'The link must survive truncation intact.');
        $this->assertStringContainsString('Prescription for', $out, 'A bare link reads as spam.');
    }

    /** The link in the message has to be the one that opens the page. */
    public function test_short_link_opens_the_patient_copy_without_a_login(): void
    {
        $url = $this->shareUrl();

        $this->assertStringContainsString('/p/', $url);

        $this->get($url)
            ->assertOk()
            ->assertSee(self::LONG_PATIENT_NAME)
            ->assertSee('NAPA')
            ->assertSee('Take after meals');
    }

    public function test_expired_token_stops_working(): void
    {
        $url = $this->shareUrl();

        $this->travelTo(now()->addHours(Prescription::SHARE_LINK_EXPIRY_HOURS + 1));

        $this->get($url)->assertNotFound();
    }

    public function test_unknown_token_is_not_found(): void
    {
        tenancy()->initialize($this->tenant);

        $this->get('http://'.self::LONG_DOMAIN.'/p/aaaaaaaaaa')->assertNotFound();
    }

    /**
     * The token is looked up through the tenant global scope, so one clinic's
     * link must not resolve on another clinic's host.
     */
    public function test_token_does_not_resolve_on_another_tenants_host(): void
    {
        $token = $this->onTenantHost(fn () => $this->prescription->shareToken());

        $other = Tenant::create(['id' => 'other-clinic', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'other-clinic.localhost', 'tenant_id' => $other->id]);

        $this->get('http://other-clinic.localhost/p/'.$token)->assertNotFound();
    }

    /**
     * Re-sending must not break the link already sitting in the patient's
     * thread; once expired, a new token is issued and the old one dies.
     */
    public function test_resending_reuses_a_live_token_and_rotates_an_expired_one(): void
    {
        $first = $this->onTenantHost(fn () => $this->prescription->shareToken());

        $this->assertSame($first, $this->onTenantHost(fn () => $this->prescription->fresh()->shareToken()));

        $this->travelTo(now()->addHours(Prescription::SHARE_LINK_EXPIRY_HOURS + 1));

        $second = $this->onTenantHost(fn () => $this->prescription->fresh()->shareToken());

        $this->assertNotSame($first, $second);
        $this->get('http://'.self::LONG_DOMAIN.'/p/'.$first)->assertNotFound();
        $this->get('http://'.self::LONG_DOMAIN.'/p/'.$second)->assertOk();
    }

    private function shareUrl(): string
    {
        // Token minting needs tenancy; the absolute URL itself comes from
        // Domain / APP_URL (TenancyUrl::publicAbsolute), not the request host.
        return $this->onTenantHost(fn () => $this->prescription->shareUrl());
    }

    /**
     * Initialise tenancy the way a staff request would (token mint + scopes).
     */
    private function onTenantHost(callable $callback, ?string $host = null): mixed
    {
        tenancy()->initialize($this->tenant);
        $this->app['request']->headers->set('HOST', $host ?? self::LONG_DOMAIN);

        return $callback();
    }
}
