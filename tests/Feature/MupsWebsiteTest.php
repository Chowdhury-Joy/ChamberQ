<?php

namespace Tests\Feature;

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
            ->assertSee('/images/mups/mups-hero-surgery.jpg', false)
            ->assertSee('/images/mups/favicon.svg', false)
            ->assertSee('Spine &amp; Back', false)
            ->assertSee('Bangladesh’s first dedicated pain centre')
            ->assertSee('Book a serial')
            ->assertSee('Rina S.')
            ->assertSee('data-review-scroll', false)
            ->assertSee('Book appointment');
    }

    public function test_inner_pages_from_drmups_are_published(): void
    {
        $this->get('http://mups.localhost/centres')
            ->assertOk()
            ->assertSee('Two centres. One standard of care.')
            ->assertSee('Panchlaish')
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
        $this->assertTrue($tenant->hasFeature('bangla_homepage'));
        $this->assertSame(\App\Models\Tenant::ETA_LIVE_AVERAGE, $tenant->eta_model);
        $this->assertSame(\App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE, $tenant->call_announce_mode);
        $this->assertSame(\App\Models\Tenant::QUEUE_RUNNER_STAFF, $tenant->queue_runner);

        tenancy()->initialize($tenant);

        $this->assertSame(2, \App\Models\Chamber::count());
        $this->assertTrue(\App\Models\Chamber::query()->where('name', 'like', '%Panchlaish%')->exists());
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
}
