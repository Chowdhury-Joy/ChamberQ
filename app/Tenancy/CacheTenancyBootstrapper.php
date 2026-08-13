<?php

namespace App\Tenancy;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\CacheManager as TenantCacheManager;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Tenant-scope the cache without requiring Redis.
 *
 * Stancl's stock bootstrapper wraps every Cache call in tags. Redis, Memcached
 * and the in-memory array store can do that; the database store this app ships
 * with cannot — it throws "This cache store does not support tagging" the first
 * time anything remembers a value after tenancy boots (My medicines, Consult
 * Screen's shared-history cache, Filament internals).
 *
 * When tags work, keep Stancl's tagged manager so Cache::flush() stays
 * tenant-scoped. When they do not, setPrefix on the live store (database
 * cache) so keys stay chamber-scoped without rebuilding the manager.
 */
class CacheTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?CacheManager $originalCache = null;

    protected ?TenantCacheManager $tenantCache = null;

    protected ?string $originalStorePrefix = null;

    public function __construct(protected Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->resetFacadeCache();

        $this->originalCache = $this->originalCache ?? $this->app['cache'];

        $repository = $this->originalCache->store();

        if ($repository->supportsTags()) {
            $tenantCache = $this->tenantCache ??= new TenantCacheManager($this->app);
            $this->app->extend('cache', function () use ($tenantCache) {
                return $tenantCache;
            });

            return;
        }

        $store = $repository->getStore();

        if (! method_exists($store, 'getPrefix') || ! method_exists($store, 'setPrefix')) {
            return;
        }

        $this->originalStorePrefix ??= (string) $store->getPrefix();
        $store->setPrefix(
            $this->originalStorePrefix.config('tenancy.cache.tag_base').$tenant->getTenantKey().'-'
        );
    }

    public function revert(): void
    {
        $this->resetFacadeCache();

        if ($this->originalStorePrefix !== null && $this->originalCache !== null) {
            $store = $this->originalCache->store()->getStore();

            if (method_exists($store, 'setPrefix')) {
                $store->setPrefix($this->originalStorePrefix);
            }

            $this->originalStorePrefix = null;
        }

        $original = $this->originalCache;
        $this->app->extend('cache', function () use ($original) {
            return $original;
        });
        $this->originalCache = null;
    }

    private function resetFacadeCache(): void
    {
        Cache::clearResolvedInstances();
    }
}
