<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenancyUrl;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domain-based tenant hosts (e.g. solo.localhost) must initialize tenancy on
 * every web request — including Livewire POST /livewire/update — before session
 * and CSRF run. Central-domain path URLs (e.g. /solo/admin) also need tenancy
 * on Livewire polls because /livewire/update has no {tenant} segment.
 */
class InitializeTenancyForTenantHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return app(InitializeTenancyByDomain::class)->handle($request, $next);
        }

        $tenantId = $this->resolveTenantSlug($request);

        if (is_string($tenantId) && $tenantId !== '') {
            $tenant = Tenant::query()->find($tenantId);

            if ($tenant) {
                app(Tenancy::class)->initialize($tenant);
            }
        }

        return $next($request);
    }

    private function resolveTenantSlug(Request $request): ?string
    {
        $routeTenant = $request->route('tenant');

        if (is_string($routeTenant) && $routeTenant !== '') {
            return $routeTenant;
        }

        $pathTenant = $this->firstPathSegment($request->path());

        if ($pathTenant) {
            return $pathTenant;
        }

        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $refererPath = parse_url($referer, PHP_URL_PATH);

        if (! is_string($refererPath) || $refererPath === '') {
            return null;
        }

        return $this->firstPathSegment(ltrim($refererPath, '/'));
    }

    private function firstPathSegment(string $path): ?string
    {
        $segment = strtok(ltrim($path, '/'), '/');

        if (! is_string($segment) || $segment === '') {
            return null;
        }

        if (in_array($segment, TenancyUrl::reservedPathPrefixes(), true)) {
            return null;
        }

        if (! preg_match('/^'.TenancyUrl::tenantSlugPattern().'$/', $segment)) {
            return null;
        }

        return $segment;
    }
}
