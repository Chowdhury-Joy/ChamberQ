<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Tenant::create(['id' => 'alpha']);
        $this->beta = Tenant::create(['id' => 'beta']);

        Domain::create(['domain' => 'alpha.localhost', 'tenant_id' => 'alpha']);
        Domain::create(['domain' => 'beta.localhost', 'tenant_id' => 'beta']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /** A tenant admin must not be able to open a different tenant's panel. */
    public function test_tenant_admin_cannot_access_another_tenants_panel(): void
    {
        $alphaAdmin = User::create([
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'tenant_id' => 'alpha',
        ]);

        $panel = filament()->getPanel('tenantAdmin');

        tenancy()->initialize($this->alpha);
        $this->assertTrue($alphaAdmin->canAccessPanel($panel), 'Own panel should be reachable.');

        tenancy()->initialize($this->beta);
        $this->assertFalse($alphaAdmin->canAccessPanel($panel), 'Another tenant panel must be refused.');
    }

    public function test_patient_and_super_admin_roles_cannot_reach_the_tenant_panel(): void
    {
        tenancy()->initialize($this->alpha);

        $patient = User::create([
            'name' => 'Patient', 'email' => 'p@alpha.test',
            'password' => Hash::make('secret'), 'role' => 'patient', 'tenant_id' => 'alpha',
        ]);

        $this->assertFalse($patient->canAccessPanel(filament()->getPanel('tenantAdmin')));
        $this->assertFalse($patient->canAccessPanel(filament()->getPanel('superAdmin')));
    }

    /** Supplying a foreign tenant_id must not place the record in that tenant. */
    public function test_tenant_id_cannot_be_forged_on_create(): void
    {
        tenancy()->initialize($this->alpha);

        $doctor = Doctor::create(['name' => 'Dr. Forged', 'tenant_id' => 'beta']);

        $this->assertSame('alpha', $doctor->fresh()->tenant_id);
    }

    public function test_tenant_id_cannot_be_changed_on_an_existing_record(): void
    {
        tenancy()->initialize($this->alpha);
        $doctor = Doctor::create(['name' => 'Dr. Stay']);

        $this->expectException(RuntimeException::class);

        $doctor->tenant_id = 'beta';
        $doctor->save();
    }

    public function test_read_only_tenant_cannot_create_bookings_but_can_still_be_viewed(): void
    {
        $this->beta->update(['billing_status' => 'read_only']);

        tenancy()->initialize($this->beta);
        $chamber = Chamber::create(['name' => 'Beta Chamber']);
        $doctor = Doctor::create(['name' => 'Dr. Beta']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id, 'doctor_id' => $doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 5,
        ]);
        tenancy()->end();

        // The public site stays viewable.
        $this->get('http://beta.localhost/book')->assertOk();

        // New bookings are refused.
        $this->postJson('http://beta.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $session->id,
            'booking_date' => Carbon::now()->next(1)->format('Y-m-d'),
            'patient_name' => 'Blocked Patient',
            'patient_phone' => '01712345678',
        ])->assertStatus(422);

        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    public function test_suspended_tenant_cannot_create_bookings(): void
    {
        $this->beta->update(['billing_status' => 'suspended']);

        tenancy()->initialize($this->beta);
        $chamber = Chamber::create(['name' => 'Suspended Chamber']);
        $doctor = Doctor::create(['name' => 'Dr. Suspended']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id, 'doctor_id' => $doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 5,
        ]);
        tenancy()->end();

        $this->get('http://beta.localhost/book')->assertOk();

        $this->postJson('http://beta.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $session->id,
            'booking_date' => Carbon::now()->next(1)->format('Y-m-d'),
            'patient_name' => 'Blocked Patient',
            'patient_phone' => '01712345678',
        ])->assertStatus(422)->assertJsonPath('code', 'closed');

        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    public function test_past_due_tenant_cannot_create_bookings(): void
    {
        $this->assertFalse($this->beta->fresh()->fill(['billing_status' => 'past_due'])->acceptsBookings());
        $this->assertTrue($this->beta->fresh()->fill(['billing_status' => 'active'])->acceptsBookings());
        $this->assertTrue($this->beta->fresh()->fill(['billing_status' => 'trial'])->acceptsBookings());
    }

    public function test_booking_rejects_a_non_bangladeshi_phone_number(): void
    {
        tenancy()->initialize($this->alpha);
        $session = $this->sessionForAlpha();
        tenancy()->end();

        $this->postJson('http://alpha.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $session->id,
            'booking_date' => Carbon::now()->next(1)->format('Y-m-d'),
            'patient_name' => 'Wrong Phone',
            'patient_phone' => '+1 555 0100',
        ])->assertStatus(422)->assertJsonValidationErrors('patient_phone');
    }

    public function test_rich_text_script_payloads_are_stripped_on_save(): void
    {
        tenancy()->initialize($this->alpha);

        $page = WebPage::create([
            'title' => 'About',
            'slug' => 'about',
            'is_published' => true,
            'content' => [[
                'type' => 'rich_text',
                'data' => ['content' => '<p>Hello<script>alert(1)</script></p>'
                    . '<a href="javascript:alert(2)">bad link</a>'
                    . '<img src=x onerror="alert(3)">'],
            ]],
        ]);

        $stored = $page->fresh()->content[0]['data']['content'];

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertStringContainsString('Hello', $stored);
    }

    /**
     * Payloads that naive tag-stripping and regex filters commonly let through.
     *
     * @dataProvider evasionPayloads
     */
    public function test_sanitiser_resists_common_xss_evasions(string $payload): void
    {
        tenancy()->initialize($this->alpha);

        $page = WebPage::create([
            'title' => 'Evasion', 'slug' => 'evasion', 'is_published' => true,
            'content' => [['type' => 'rich_text', 'data' => ['content' => $payload]]],
        ]);

        $stored = strtolower($page->fresh()->content[0]['data']['content']);

        foreach (['<script', '<iframe', '<svg', '<object', '<embed', 'javascript:', 'onerror', 'onload', 'onclick', 'srcdoc'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored, "Leaked [{$forbidden}] from: {$payload}");
        }
    }

    public static function evasionPayloads(): array
    {
        return [
            'nested script tags' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'uppercase tag' => ['<SCRIPT>alert(1)</SCRIPT>'],
            'svg onload' => ['<svg/onload=alert(1)>'],
            'iframe srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
            'entity-encoded scheme' => ['<a href="&#106;avascript:alert(1)">x</a>'],
            'whitespace in scheme' => ['<a href="java\tscript:alert(1)">x</a>'],
            'malformed attribute quoting' => ['<a href=javascript:alert(1)>x</a>'],
            'body onload' => ['<body onload=alert(1)>'],
            'style expression' => ['<div style="width:expression(alert(1))">x</div>'],
            'object data' => ['<object data="javascript:alert(1)"></object>'],
        ];
    }

    public function test_slugs_are_normalised_so_authored_pages_resolve(): void
    {
        tenancy()->initialize($this->alpha);

        // Staff typing "about" (no slash) must still resolve at /about.
        WebPage::create(['title' => 'About', 'slug' => 'about', 'is_published' => true, 'content' => []]);
        tenancy()->end();

        $this->get('http://alpha.localhost/about')->assertOk();
    }

    public function test_builder_cta_javascript_urls_are_stripped_on_save(): void
    {
        tenancy()->initialize($this->alpha);

        $page = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'hero',
                'data' => [
                    'headline' => 'Care',
                    'cta_link' => 'javascript:alert(1)',
                    'secondary_cta_link' => 'javascript:alert(2)',
                    'image_url' => 'https://example.com/hero.jpg',
                ],
            ], [
                'type' => 'cta_banner',
                'data' => [
                    'headline' => 'Book',
                    'cta_link' => 'javascript:alert(3)',
                ],
            ]],
        ]);

        $hero = $page->fresh()->content[0]['data'];
        $banner = $page->fresh()->content[1]['data'];

        $this->assertSame('', $hero['cta_link']);
        $this->assertSame('', $hero['secondary_cta_link']);
        $this->assertSame('https://example.com/hero.jpg', $hero['image_url']);
        $this->assertSame('', $banner['cta_link']);
    }

    public function test_safe_url_allowlist_accepts_relative_and_https(): void
    {
        $this->assertSame('/book', \App\Support\SafeUrl::href('/book'));
        $this->assertSame('#services', \App\Support\SafeUrl::href('#services'));
        $this->assertSame('https://maps.google.com/?q=Dhaka', \App\Support\SafeUrl::href('https://maps.google.com/?q=Dhaka'));
        $this->assertSame('tel:+8801712345678', \App\Support\SafeUrl::href('tel:+8801712345678'));
        $this->assertSame('#', \App\Support\SafeUrl::href('javascript:alert(1)'));
        $this->assertSame('#', \App\Support\SafeUrl::href('data:text/html,<script>alert(1)</script>'));
        $this->assertSame('#', \App\Support\SafeUrl::href('//evil.example/path'));
    }

    private function sessionForAlpha(): ScheduleSession
    {
        $chamber = Chamber::create(['name' => 'Alpha Chamber']);
        $doctor = Doctor::create(['name' => 'Dr. Alpha']);

        return ScheduleSession::create([
            'chamber_id' => $chamber->id, 'doctor_id' => $doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 5,
        ]);
    }
}
