<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards central path tenancy (/solo/book) vs custom-domain tenancy (solo.localhost/book).
 */
class TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['id' => 'acme']);
        Domain::create(['domain' => 'acme.localhost', 'tenant_id' => $tenant->id]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_tenant_routes_resolve_on_a_custom_domain(): void
    {
        $this->get('http://acme.localhost/book')->assertOk();
    }

    public function test_tenant_routes_resolve_on_central_platform_path(): void
    {
        $this->get('http://localhost/acme/book')->assertOk();
    }

    public function test_tenant_home_resolves_on_central_platform_path(): void
    {
        $this->get('http://localhost/acme/')
            ->assertDontSee('Laravel', escape: false);
    }

    public function test_tenant_routes_do_not_resolve_on_the_central_domain_without_slug(): void
    {
        $this->get('http://localhost/book')->assertNotFound();
    }

    public function test_central_routes_resolve_on_the_central_domain(): void
    {
        $this->get('http://localhost/')->assertOk();
    }

    public function test_unknown_tenant_slug_on_central_domain_returns_not_found(): void
    {
        $this->get('http://localhost/not-a-tenant/book')->assertNotFound();
    }

    public function test_reserved_central_segment_admin_is_not_treated_as_tenant_slug(): void
    {
        $this->get('http://localhost/admin/login')->assertOk();
    }

    public function test_central_root_does_not_shadow_the_tenant_landing_page_on_custom_domain(): void
    {
        $this->get('http://acme.localhost/')
            ->assertDontSee('Laravel', escape: false);
    }
}
