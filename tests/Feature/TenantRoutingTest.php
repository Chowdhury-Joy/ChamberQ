<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the central/tenant route separation required by the spec.
 *
 * These assertions exist because the entire tenant route file was silently
 * unregistered at one point (the TenancyServiceProvider was missing from
 * bootstrap/providers.php) while the whole test suite stayed green.
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

    public function test_tenant_routes_resolve_on_a_tenant_domain(): void
    {
        $this->get('http://acme.localhost/book')->assertOk();
    }

    public function test_tenant_routes_do_not_resolve_on_the_central_domain(): void
    {
        $this->get('http://localhost/book')->assertNotFound();
    }

    public function test_central_routes_resolve_on_the_central_domain(): void
    {
        $this->get('http://localhost/')->assertOk();
    }

    public function test_central_root_does_not_shadow_the_tenant_landing_page(): void
    {
        // With no WebPage published the tenant root is a 404 placeholder — the
        // important part is that it is NOT the central welcome page.
        $this->get('http://acme.localhost/')
            ->assertDontSee('Laravel', escape: false);
    }
}
