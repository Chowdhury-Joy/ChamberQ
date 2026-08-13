<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheTenancyBootstrapperTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_tagged_array_store_still_isolates_tenants(): void
    {
        $alpha = Tenant::create(['id' => 'cache-alpha', 'plan_tier' => 'solo']);
        $beta = Tenant::create(['id' => 'cache-beta', 'plan_tier' => 'solo']);

        tenancy()->initialize($alpha);
        Cache::put('probe', 'alpha', 60);
        $this->assertSame('alpha', Cache::get('probe'));

        tenancy()->initialize($beta);
        $this->assertNull(Cache::get('probe'));
        Cache::put('probe', 'beta', 60);

        tenancy()->initialize($alpha);
        $this->assertSame('alpha', Cache::get('probe'));
    }

    public function test_database_cache_without_tags_does_not_throw_and_isolates_tenants(): void
    {
        config(['cache.default' => 'database']);
        Cache::clearResolvedInstances();

        $alpha = Tenant::create(['id' => 'db-cache-alpha', 'plan_tier' => 'solo']);
        $beta = Tenant::create(['id' => 'db-cache-beta', 'plan_tier' => 'solo']);

        tenancy()->initialize($alpha);
        Cache::put('probe', 'alpha', 60);
        $this->assertSame('alpha', Cache::get('probe'));

        tenancy()->initialize($beta);
        $this->assertNull(Cache::get('probe'));
        Cache::put('probe', 'beta', 60);
        $this->assertSame('beta', Cache::get('probe'));

        tenancy()->initialize($alpha);
        $this->assertSame('alpha', Cache::get('probe'));
    }
}
