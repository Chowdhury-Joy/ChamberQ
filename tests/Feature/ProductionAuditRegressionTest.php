<?php

namespace Tests\Feature;

use App\Http\Controllers\PatientAuthController;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\PatientAccount;
use App\Models\PatientOtpCode;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\PatientOtpService;
use App\Services\PlatformPatientHistoryService;
use App\Support\PushEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regressions for the production audit of 2026-08-15.
 *
 * Each test here failed before its fix. They are grouped in one class because
 * they share the "one tenant with one booking" fixture, not because they are
 * one feature.
 */
class ProductionAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'auditclinic']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create([
            'name' => 'Audit Chamber',
            'address' => 'Dhaka',
        ]);

        $doctor = Doctor::create([
            'name' => 'Audit Doctor',
        ]);

        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'session_name' => 'Morning',
            'day_of_week' => (int) now()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => now()->toDateString(),
            'patient_name' => 'Fatima Rahman',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        tenancy()->end();
    }

    /**
     * The one that mattered most: an account with nothing to match on used to
     * receive a query with no WHERE clause at all, because Laravel drops a
     * nested where() whose closure added no constraints — and every query in
     * that service runs withoutGlobalScopes(). Result: every booking of every
     * chamber on the platform.
     */
    public function test_patient_account_with_unmatchable_phone_sees_no_bookings(): void
    {
        $account = new PatientAccount(['name' => 'Nobody']);
        // A stored phone that BdPhone rejects, which is what emptied both the
        // phone list and the patient-id list.
        $account->forceFill(['phone' => '00000'])->save();

        $history = app(PlatformPatientHistoryService::class);

        $this->assertCount(0, $history->serialsFor($account));
        $this->assertCount(0, $history->historyFor($account));
    }

    public function test_patient_account_still_sees_its_own_bookings(): void
    {
        $account = PatientAccount::create([
            'phone' => '01712345678',
            'name' => 'Fatima Rahman',
        ]);

        $serials = app(PlatformPatientHistoryService::class)->serialsFor($account);

        $this->assertCount(1, $serials);
        $this->assertSame($this->booking->id, $serials->first()['id']);
    }

    /**
     * Booking a serial is public, so a booking id is free to obtain. Without
     * this the caller chose any URL the server would later POST to.
     */
    public function test_push_subscribe_rejects_internal_endpoints(): void
    {
        foreach ([
            'http://169.254.169.254/latest/meta-data/',
            'https://127.0.0.1/push',
            'https://10.0.0.5/push',
            'https://localhost/push',
            'https://redis.internal/push',
            'https://fcm.googleapis.com:6379/push',
            'https://user:pass@fcm.googleapis.com/push',
        ] as $endpoint) {
            $this->postJson('http://localhost/auditclinic/api/queue/'.$this->booking->id.'/push', [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ])->assertStatus(422, "expected {$endpoint} to be rejected");
        }

        $this->assertDatabaseCount('booking_push_subscriptions', 0);
    }

    public function test_push_subscribe_still_accepts_a_real_push_service(): void
    {
        $this->postJson('http://localhost/auditclinic/api/queue/'.$this->booking->id.'/push', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fatima-token',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertOk();

        $this->assertDatabaseCount('booking_push_subscriptions', 1);
    }

    public function test_push_endpoint_helper_rules(): void
    {
        $this->assertTrue(PushEndpoint::isAllowed('https://updates.push.services.mozilla.com/wpush/v2/abc'));
        $this->assertTrue(PushEndpoint::isAllowed('https://web.push.apple.com/abc'));

        $this->assertFalse(PushEndpoint::isAllowed(null));
        $this->assertFalse(PushEndpoint::isAllowed(''));
        $this->assertFalse(PushEndpoint::isAllowed('not-a-url'));
        $this->assertFalse(PushEndpoint::isAllowed('http://fcm.googleapis.com/push'));
        $this->assertFalse(PushEndpoint::isAllowed('file:///etc/passwd'));
        $this->assertFalse(PushEndpoint::isAllowed('https://metadata/computeMetadata/v1/'));
    }

    /**
     * Guard, not proof: the tenant lookup the `web` group now runs on every
     * request must not turn a missing page into a server error. The `try` that
     * covers a database fault cannot be exercised without breaking the
     * connection, so this only pins the normal path.
     */
    public function test_unknown_central_path_is_a_404_not_a_500(): void
    {
        $this->get('http://localhost/no-such-chamber')->assertNotFound();
    }

    /**
     * `body` and `bio` are sanitised by a model `saving` hook — which
     * `DataImportService` skips entirely, because a backup restore writes with
     * `DB::table()->upsert()`. The public views rendered those columns raw, so
     * the single guard the whole clinic site relied on was one code path away
     * from not existing. Sanitising at render puts it where the code converges.
     */
    public function test_clinic_content_written_around_the_model_is_still_sanitised(): void
    {
        $clinic = Tenant::create(['id' => 'auditclinicsite', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'auditclinicsite.localhost', 'tenant_id' => $clinic->id]);

        $payload = '<p>Heart care</p><script>alert(1)</script>';

        // Exactly how a restore writes: straight to the table, no model events.
        DB::table('departments')->insert([
            'tenant_id' => $clinic->id,
            'title' => 'Cardiology',
            'slug' => 'cardiology',
            'body' => $payload,
            'is_published' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('http://auditclinicsite.localhost/departments/cardiology')
            ->assertOk()
            ->assertSee('Heart care', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('alert(1)', false);
    }

    /**
     * Referer is caller-controlled. Only Livewire's tenant-less update endpoint
     * may fall back to it, and only for a referrer on this same host.
     */
    public function test_offsite_referer_cannot_select_a_tenant(): void
    {
        $this->get('http://localhost/', [
            'Referer' => 'https://evil.example/auditclinic/book',
        ])->assertOk();

        $this->assertFalse(tenancy()->initialized);
    }

    public function test_spent_otp_rows_are_pruned_on_the_next_send(): void
    {
        PatientOtpCode::create([
            'phone' => '01712345678',
            'code_hash' => Hash::make('111111'),
            'expires_at' => now()->subHour(),
            'consumed_at' => now()->subHour(),
        ]);

        $this->assertSame(1, PatientOtpCode::query()->count());

        app(PatientOtpService::class)->send('01712345678');

        // The stale consumed row is gone; only the freshly minted live one is left.
        $this->assertSame(1, PatientOtpCode::query()->count());
        $this->assertNull(PatientOtpCode::query()->first()->consumed_at);
    }

    public function test_patient_logout_invalidates_the_session(): void
    {
        $account = PatientAccount::create([
            'phone' => '01712345678',
            'name' => 'Fatima Rahman',
        ]);

        // Driven directly rather than over HTTP: the test session driver is
        // `array`, which throws the store away between requests, so a session
        // id compared across two test requests always differs and would have
        // passed with or without the fix.
        $session = new Store('chamberq_session', new ArraySessionHandler(120));
        $session->start();
        $session->put('patient_otp_phone', '01712345678');
        $idBeforeLogout = $session->getId();

        $request = Request::create('http://localhost/me/logout', 'POST');
        $request->setLaravelSession($session);

        auth('patient')->login($account);
        app(PatientAuthController::class)->logout($request);

        $this->assertGuest('patient');
        $this->assertFalse($session->has('patient_otp_phone'));

        // The point of the fix: the session id the browser arrived with is
        // retired. `forget()` alone left it valid and reusable.
        $this->assertNotSame($idBeforeLogout, $session->getId());
    }
}
