<?php

namespace Tests\Feature;

use App\Models\PharmacyItem;
use Database\Seeders\MupsDemoSeeder;
use Database\Seeders\MupsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MupsWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MupsSeeder::class);
    }

    public function test_homepage_uses_clinic_look_and_drmups_copy(): void
    {
        $this->get('http://mups.localhost/')
            ->assertOk()
            ->assertSee('Relief without surgery')
            ->assertSeeInOrder([
                'Relief without surgery',
                'MUPS — Dhaka and Chittagong',
                'The founder',
                'Dr. Mohammad Moin Uddin',
            ])
            ->assertSee('--hero-photo: url(', false)
            ->assertSee('/images/mups/mups-hero-surgery.jpg', false)
            ->assertSee('/images/mups/favicon.svg', false)
            ->assertSee('Spine &amp; Back', false)
            ->assertSee('Bangladesh’s first dedicated pain centre')
            ->assertSee('Book a serial')
            ->assertSee('Rina S.')
            ->assertSee('data-review-scroll', false)
            ->assertSee('Book appointment');
    }

    public function test_hero_form_submits_to_the_book_counter_not_the_homepage(): void
    {
        $domainHome = $this->get('http://mups.localhost/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<form[^>]*id="book"[^>]*action="\/book"/', $domainHome);
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*id="book"[^>]*action="\/"/', $domainHome);

        $pathHome = $this->get('http://localhost/mups/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<form[^>]*id="book"[^>]*action="\/mups\/book"/', $pathHome);
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*id="book"[^>]*action="\/mups\/"/', $pathHome);
    }

    public function test_hero_asks_for_phone_before_name(): void
    {
        $html = $this->get('http://mups.localhost/')
            ->assertOk()
            ->getContent();

        $phone = strpos($html, 'id="hero-phone"');
        $name = strpos($html, 'id="hero-patient-name"');

        $this->assertNotFalse($phone);
        $this->assertNotFalse($name);
        $this->assertLessThan($name, $phone);
    }

    public function test_team_doctor_grid_does_not_stretch_a_single_card(): void
    {
        $this->get('http://mups.localhost/')
            ->assertOk()
            ->assertSee('doc-grid--team', false)
            ->assertSee('data-card-count="1"', false);

        $this->get('http://mups.localhost/doctors')
            ->assertOk()
            ->assertSee('doc-grid--team', false);

        $css = file_get_contents(public_path('css/clinic-clireo.css'));
        $this->assertStringContainsString('repeat(3, minmax(0, 1fr))', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.doc-grid--team\.grid-cards\[data-card-count="1"\]\s*\{[^}]*grid-template-columns:\s*1fr/',
            $css
        );
    }

    public function test_a_non_hex_theme_color_cannot_blank_the_hero_photo(): void
    {
        \App\Models\Tenant::query()->whereKey(MupsSeeder::TENANT_ID)->update([
            'theme_color' => 'QA sweep',
        ]);

        $this->get('http://mups.localhost/')
            ->assertOk()
            ->assertSee('--hero-photo: url(', false)
            ->assertSee('/images/mups/mups-hero-surgery.jpg', false)
            ->assertDontSee('--brand: QA sweep', false)
            ->assertSee('--brand: '.\App\Models\Tenant::DEFAULT_THEME_COLOR, false);
    }

    public function test_inner_pages_from_drmups_are_published(): void
    {
        $this->get('http://mups.localhost/centres')
            ->assertOk()
            ->assertSee('Two centres. One standard of care.')
            ->assertSee('Mehedibag')
            ->assertSee('Uttara')
            ->assertDontSee('Epic Healthcare');

        $this->get('http://mups.localhost/fellowship')
            ->assertOk()
            ->assertSee('FIPP-track fellowship')
            ->assertSee('৳ 3,50,000');

        $this->get('http://mups.localhost/gallery')
            ->assertOk()
            ->assertSee('Moments from the practice');

        $this->get('http://mups.localhost/contact')
            ->assertOk()
            ->assertSee('A phone call away')
            ->assertSee('hello@mups.com.bd');

        $this->get('http://mups.localhost/appointment')
            ->assertOk()
            ->assertSee('under 60 seconds');
    }

    public function test_treatments_doctors_and_media_use_clinic_cms_routes(): void
    {
        $this->get('http://mups.localhost/departments')
            ->assertOk()
            ->assertSee('Spine &amp; Back', false);

        $this->get('http://mups.localhost/departments/spine-back')
            ->assertOk()
            ->assertSee('Epidural');

        $this->get('http://mups.localhost/doctors')
            ->assertOk()
            ->assertSee('Dr. Mohammad Moin Uddin');

        $this->get('http://mups.localhost/blog')
            ->assertOk()
            ->assertSee('When back pain is more than just back pain');
    }

    public function test_nav_links_inner_pages_with_tenant_prefix(): void
    {
        $this->get('http://localhost/mups/')
            ->assertOk()
            ->assertSee('href="/mups/centres"', false)
            ->assertSee('href="/mups/fellowship"', false)
            ->assertSee('href="/mups/gallery"', false)
            ->assertSee('href="/mups/contact"', false)
            ->assertSee('href="/mups/appointment"', false)
            ->assertSee('href="/mups/departments"', false)
            ->assertSee('href="/mups/doctors"', false)
            ->assertSee('href="/mups/blog"', false)
            ->assertDontSee('href="/centres"', false);

        $this->get('http://mups.localhost/')
            ->assertOk()
            ->assertSee('data-card-count="1"', false)
            ->assertSee('The founder');
    }

    public function test_hero_asks_which_centre_before_listing_sittings(): void
    {
        $html = $this->get('http://mups.localhost/')
            ->assertOk()
            ->assertSee('id="hero-chamber"', false)
            ->assertSee('Which centre?')
            ->assertSee('Mehedibag')
            ->assertSee('Uttara')
            ->getContent();

        $this->assertTrue(
            (bool) preg_match('/id="hero-date"[^>]*>.*?<\/select>/s', $html, $dateMatch),
            'Hero date dropdown missing'
        );
        $this->assertStringContainsString('Select date', $dateMatch[0]);
        $this->assertStringContainsString('disabled selected', $dateMatch[0]);
        $this->assertStringNotContainsString('type="date"', $dateMatch[0]);

        $this->assertTrue(
            (bool) preg_match('/id="hero-session"[^>]*>.*?<\/select>/s', $html, $match),
            'Hero session dropdown missing'
        );
        $this->assertStringContainsString('data-chamber=', $match[0]);
        $this->assertStringContainsString('Visit', $match[0]);
        $this->assertStringNotContainsString('Intervention', $match[0]);
        $this->assertStringNotContainsString('Counseling', $match[0]);
    }

    public function test_hero_prefers_the_chosen_centre_when_session_belongs_elsewhere(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        tenancy()->initialize($tenant);
        $mehedibag = \App\Models\Chamber::query()->where('name', 'like', '%Mehedibag%')->first();
        $uttara = \App\Models\Chamber::query()->where('name', 'like', '%Uttara%')->first();
        $mehedibagSession = \App\Models\ScheduleSession::query()
            ->publiclyBookable()
            ->where('chamber_id', $mehedibag->id)
            ->first();
        $doctorId = (string) $mehedibagSession->doctor_id;
        tenancy()->end();

        $this->followingRedirects()
            ->post('http://mups.localhost/book', [
                'name' => 'Fatima Rahman',
                'phone' => '01712345678',
                'doctor' => $doctorId,
                'chamber' => (string) $uttara->id,
                'session' => (string) $mehedibagSession->id,
                'date' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertSee('"chamber":"'.$uttara->id.'"', false)
            ->assertDontSee('"session":"'.$mehedibagSession->id.'"', false);
    }

    public function test_homepage_post_with_hero_fields_still_reaches_the_wizard(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        tenancy()->initialize($tenant);
        $mehedibag = \App\Models\Chamber::query()->where('name', 'like', '%Mehedibag%')->first();
        $session = \App\Models\ScheduleSession::query()
            ->publiclyBookable()
            ->where('chamber_id', $mehedibag->id)
            ->first();
        $payload = [
            'name' => 'Fatima Rahman',
            'phone' => '01712345678',
            'doctor' => (string) $session->doctor_id,
            'chamber' => (string) $mehedibag->id,
            'session' => (string) $session->id,
            'date' => now()->next($session->day_of_week)->toDateString(),
        ];
        tenancy()->end();

        $this->post('http://mups.localhost/', $payload)
            ->assertRedirect('http://mups.localhost/book');

        $this->post('http://localhost/mups/', $payload)
            ->assertRedirect('/mups/book');

        $this->followingRedirects()
            ->post('http://mups.localhost/', $payload)
            ->assertOk()
            ->assertSee('Fatima Rahman', false)
            ->assertSee('01712345678', false);
    }

    public function test_booking_wizard_is_open(): void
    {
        $this->get('http://mups.localhost/book')
            ->assertOk();
    }

    public function test_two_branches_have_queue_stations_referrals_and_hr(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);

        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->hasFrontDoor());
        $this->assertTrue($tenant->hasLiveQueue());
        $this->assertTrue($tenant->hasPrescription());
        $this->assertTrue($tenant->hasStations());
        $this->assertTrue($tenant->hasReferrals());
        $this->assertTrue($tenant->hasHr());
        $this->assertTrue($tenant->hasPharmacy());
        $this->assertTrue($tenant->hasFeature('bangla_homepage'));
        $this->assertSame(\App\Models\Tenant::ETA_LIVE_AVERAGE, $tenant->eta_model);
        $this->assertSame(\App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE, $tenant->call_announce_mode);
        $this->assertSame(\App\Models\Tenant::QUEUE_RUNNER_STAFF, $tenant->queue_runner);

        tenancy()->initialize($tenant);

        $this->assertSame(2, \App\Models\Chamber::count());
        $this->assertTrue(\App\Models\Chamber::query()->where('name', 'like', '%Mehedibag%')->exists());
        $this->assertTrue(\App\Models\Chamber::query()->where('name', 'like', '%Uttara%')->exists());
        $this->assertFalse(\App\Models\Chamber::query()->where('name', 'like', '%Epic%')->exists());

        $this->assertGreaterThan(0, \App\Models\ScheduleSession::query()->where('kind', \App\Models\ScheduleSession::KIND_VISIT)->count());
        $this->assertGreaterThan(0, \App\Models\ScheduleSession::query()->where('kind', \App\Models\ScheduleSession::KIND_INTERVENTION)->count());
        $this->assertGreaterThan(0, \App\Models\ScheduleSession::query()->where('kind', \App\Models\ScheduleSession::KIND_COUNSELING)->count());
        $this->assertGreaterThan(0, \App\Models\FeeCatalogItem::query()->count());
        $this->assertGreaterThan(0, \App\Models\ReferringDoctor::query()->count());
        $this->assertGreaterThan(0, \App\Models\Employee::query()->count());

        $chamber = \App\Models\Chamber::query()->orderBy('id')->first();
        tenancy()->end();

        $this->get('http://mups.localhost/screen/chamber/'.$chamber->id)
            ->assertOk();
    }

    public function test_pharmacy_shop_matches_the_desk_price_list(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        $this->assertTrue($tenant->hasPharmacy());

        tenancy()->initialize($tenant);

        $expected = MupsSeeder::pharmacyShopRows();
        $this->assertCount(9, $expected);

        $items = PharmacyItem::query()->orderBy('chamber_id')->orderBy('sort_order')->get();
        $this->assertCount(18, $items);

        $chambers = \App\Models\Chamber::query()->orderBy('id')->get();
        $this->assertCount(2, $chambers);

        foreach ($chambers as $chamber) {
            $atCentre = $items->where('chamber_id', $chamber->id)->values();
            $this->assertCount(9, $atCentre);

            foreach ($expected as $index => $row) {
                $item = $atCentre[$index];
                $this->assertSame($row['name'], $item->name);
                $this->assertSame($row['sell_price_taka'], $item->sell_price_taka);
                $this->assertSame($row['company_share_taka'], $item->company_share_taka);
                $this->assertSame(MupsDemoSeeder::demoStockQty($row['name']), $item->qty_on_hand);
                $this->assertTrue($item->is_active);
                $this->assertSame(
                    $row['sell_price_taka'] - $row['company_share_taka'],
                    $item->shopCutTaka(),
                );
                $this->assertTrue($item->deliveries()->exists());
            }
        }

        tenancy()->end();
    }

    public function test_booking_horizon_follows_the_tenant_setting_not_a_hardcoded_clinic(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        $this->assertNull($tenant->patient_booking_horizon_days);

        tenancy()->initialize($tenant);
        $platform = \App\Models\PlatformSetting::platformHorizonDays();
        $this->assertSame($platform, \App\Models\PlatformSetting::patientBookingHorizonDays());

        $tenant->update(['patient_booking_horizon_days' => 3]);
        $this->assertSame(3, \App\Models\PlatformSetting::patientBookingHorizonDays());
        $this->assertSame(now()->addDays(3)->toDateString(), \App\Models\PlatformSetting::onlineBookingMaxDate());
        tenancy()->end();
    }

    public function test_seed_includes_a_lead_desk_login_separate_from_counter_staff(): void
    {
        $lead = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'lead@mups.local')
            ->first();

        $this->assertNotNull($lead);
        $this->assertTrue($lead->isStaff());
        $this->assertTrue(\App\Support\StaffDeskJobs::isLeadDesk($lead));
        $this->assertTrue($lead->canManageDeskStaff());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('pass', $lead->password));

        $desk = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'staff@mups.local')
            ->first();

        $this->assertNotNull($desk);
        $this->assertFalse(\App\Support\StaffDeskJobs::isLeadDesk($desk));
    }

    public function test_lead_desk_can_open_pharmacy_till_and_stock(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        tenancy()->initialize($tenant);

        $lead = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'lead@mups.local')
            ->first();

        $this->actingAs($lead);
        $this->assertTrue(\App\Support\PharmacyAccess::canRunCounter($lead));
        $this->assertTrue(\App\Support\PharmacyAccess::canManageStock($lead));
        $this->assertTrue(\App\Filament\TenantAdmin\Pages\PharmacyCounter::canAccess());
        $this->assertTrue(\App\Filament\TenantAdmin\Resources\PharmacyItems\PharmacyItemResource::canViewAny());

        $mehedibag = \App\Models\Chamber::query()->where('name', 'like', '%Mehedibag%')->first();
        $uttara = \App\Models\Chamber::query()->where('name', 'like', '%Uttara%')->first();
        $visible = \App\Support\PharmacyAccess::scopedItems($lead)->pluck('chamber_id')->unique()->sort()->values();
        $this->assertEquals([$mehedibag->id], $visible->all());
        $this->assertFalse($visible->contains($uttara->id));

        tenancy()->end();
    }

    public function test_each_centre_has_its_own_desk_logins(): void
    {
        $tenant = \App\Models\Tenant::find(MupsSeeder::TENANT_ID);
        tenancy()->initialize($tenant);

        $mehedibag = \App\Models\Chamber::query()->where('name', 'like', '%Mehedibag%')->first();
        $uttara = \App\Models\Chamber::query()->where('name', 'like', '%Uttara%')->first();

        $mehedibagLead = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'lead@mups.local')
            ->first();
        $uttaraLead = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'lead.uttara@mups.local')
            ->first();
        $uttaraDesk = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'staff.uttara@mups.local')
            ->first();
        $doctor = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('email', 'doctor@mups.local')
            ->first();

        $this->assertNotNull($uttaraLead);
        $this->assertTrue(\App\Support\StaffDeskJobs::isLeadDesk($uttaraLead));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('pass', $uttaraLead->password));
        $this->assertSame([$uttara->id], \App\Support\StaffDeskScope::chamberIdsFor($uttaraLead));
        $this->assertSame([$mehedibag->id], \App\Support\StaffDeskScope::chamberIdsFor($mehedibagLead));
        $this->assertSame([$uttara->id], \App\Support\StaffDeskScope::chamberIdsFor($uttaraDesk));
        $this->assertNull(\App\Support\StaffDeskScope::chamberIdsFor($doctor));

        $uttaraVisible = \App\Support\PharmacyAccess::scopedItems($uttaraLead)->pluck('chamber_id')->unique()->sort()->values();
        $this->assertEquals([$uttara->id], $uttaraVisible->all());

        tenancy()->end();
    }
}
