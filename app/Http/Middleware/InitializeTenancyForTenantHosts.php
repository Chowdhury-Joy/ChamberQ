<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenancyUrl;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        if ($tenantId === null) {
            return $next($request);
        }

        // A missing tenant is a 404's job, not a 500's. This runs on the whole
        // `web` group — including the request that is already on its way to an
        // error page — so letting a database fault escape here turned every
        // "page not found" into "server error" and buried the real cause.
        try {
            $tenant = Tenant::query()->find($tenantId);
        } catch (Throwable $e) {
            report($e);

            return $next($request);
        }

        if ($tenant) {
            app(Tenancy::class)->initialize($tenant);
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

        return $this->refererTenant($request);
    }

    /**
     * Tenant slug taken from the page the request came from.
     *
     * Scoped to Livewire's update endpoint, which is the only reason this
     * fallback exists: `/livewire/update` carries no `{tenant}` segment, so the
     * panel it belongs to can only be recovered from the referring page.
     *
     * Deliberately not applied to every route. `Referer` is set by the caller,
     * so a header alone could otherwise decide which practice any central page
     * was rendered as — including pages whose whole job is that no tenant is
     * active, where an initialized tenancy re-scopes `User` lookups away from
     * the central admin and logs them out.
     */
    private function refererTenant(Request $request): ?string
    {
        if (! $request->is('livewire/*')) {
            return null;
        }

        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        // Only a referrer on this same host may name a tenant — an off-site
        // page has no business choosing one.
        $refererHost = parse_url($referer, PHP_URL_HOST);

        if (! is_string($refererHost) || strcasecmp($refererHost, $request->getHost()) !== 0) {
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
