<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\TenancyUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenancyUrlPublicAbsoluteTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_tenant_uses_app_url_not_central_domains_first_host(): void
    {
        config([
            'app.url' => 'https://chamberq.example',
            'tenancy.central_domains' => ['127.0.0.1', 'localhost', 'chamberq.example'],
        ]);

        $tenant = Tenant::create(['id' => 'dr-karim', 'plan_tier' => 'solo']);

        $url = TenancyUrl::publicAbsolute($tenant->id, '/bookings/abc');

        $this->assertSame('https://chamberq.example/dr-karim/bookings/abc', $url);
        $this->assertStringNotContainsString('127.0.0.1', $url);
    }

    public function test_custom_domain_prefers_domain_row_and_https_when_app_url_is_localhost(): void
    {
        config(['app.url' => 'http://localhost']);

        $tenant = Tenant::create(['id' => 'clinic-domain', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'clinic.example', 'tenant_id' => $tenant->id]);

        $url = TenancyUrl::publicAbsolute($tenant->id, '/p/token123');

        $this->assertSame('https://clinic.example/p/token123', $url);
    }

    public function test_localhost_domain_rows_stay_http_for_local_tests(): void
    {
        config(['app.url' => 'http://localhost']);

        $tenant = Tenant::create(['id' => 'solo-local', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'solo.localhost', 'tenant_id' => $tenant->id]);

        $url = TenancyUrl::publicAbsolute($tenant->id, '/screen/9');

        $this->assertSame('http://solo.localhost/screen/9', $url);
    }

    public function test_screen_bookmark_on_path_tenant_uses_request_host_not_domain_row(): void
    {
        config(['tenancy.central_domains' => ['127.0.0.1', 'localhost']]);

        $tenant = Tenant::create(['id' => 'nusraturmi', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'nusraturmi.localhost', 'tenant_id' => $tenant->id]);

        $this->app->instance('request', Request::create('http://127.0.0.1:8000/nusraturmi/admin'));
        tenancy()->initialize($tenant);

        $url = TenancyUrl::screenBookmarkUrl($tenant->id, 81);

        $this->assertSame('http://127.0.0.1:8000/nusraturmi/screen/81', $url);
        $this->assertStringNotContainsString('nusraturmi.localhost', $url);

        tenancy()->end();
    }
}
