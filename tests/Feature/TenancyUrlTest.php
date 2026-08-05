<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenancyUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_tenant_slug_pattern_blocks_reserved_segments(): void
    {
        $pattern = TenancyUrl::tenantSlugPattern();

        $this->assertMatchesRegularExpression('/^'.$pattern.'$/', 'drkarim');
        $this->assertDoesNotMatchRegularExpression('/^'.$pattern.'$/', 'admin');
    }

    public function test_tenant_url_uses_path_prefix_on_central_domain(): void
    {
        Tenant::create(['id' => 'solo']);

        tenancy()->initialize(Tenant::find('solo'));
        request()->headers->set('HOST', 'localhost');

        $this->assertSame('/solo/book', tenant_web_url('/book'));
        $this->assertSame('/solo/', tenant_web_url('/'));
    }

    public function test_tenant_url_has_no_prefix_on_custom_domain(): void
    {
        Tenant::create(['id' => 'solo']);
        \App\Models\Domain::create(['domain' => 'solo.localhost', 'tenant_id' => 'solo']);

        $this->get('http://solo.localhost/book');

        $this->assertSame('/book', tenant_web_url('/book'));
    }

    public function test_tenant_safe_href_prefixes_relative_paths_on_central_domain(): void
    {
        Tenant::create(['id' => 'solo']);

        tenancy()->initialize(Tenant::find('solo'));
        request()->headers->set('HOST', 'localhost');

        $this->assertSame('/solo/book', tenant_safe_href('/book', '/book'));
        $this->assertSame('/solo/book?doctor=1', tenant_safe_href('/book?doctor=1', '/book'));
        $this->assertSame('https://maps.example', tenant_safe_href('https://maps.example', '/book'));
        $this->assertSame('#about', tenant_safe_href('#about', '/book'));
        $this->assertSame('/solo/book', tenant_safe_href('javascript:alert(1)', '/book'));
    }

    public function test_tenant_safe_href_keeps_root_paths_on_custom_domain(): void
    {
        Tenant::create(['id' => 'solo']);
        \App\Models\Domain::create(['domain' => 'solo.localhost', 'tenant_id' => 'solo']);

        $this->get('http://solo.localhost/book');

        $this->assertSame('/book', tenant_safe_href('/book', '/book'));
    }
}
