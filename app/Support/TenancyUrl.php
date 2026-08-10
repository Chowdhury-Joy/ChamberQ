<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class TenancyUrl
{
    /**
     * Route-name prefix used for central-domain path tenancy routes.
     */
    public const PATH_ROUTE_PREFIX = 'path.';

    /**
     * Absolute URL for a patient-facing path that may leave the browser
     * (SMS, WhatsApp, TV bookmark). Prefer the tenant's custom Domain; otherwise
     * build from APP_URL + /{tenantId}/… so path tenants never inherit
     * CENTRAL_DOMAINS[0] (often 127.0.0.1).
     *
     * Do not use for same-tab navigation — prefer route(..., absolute: false).
     */
    public static function publicAbsolute(string $tenantId, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        $host = Domain::where('tenant_id', $tenantId)->value('domain');

        if ($host) {
            return static::schemeForOutboundHost($host).'://'.$host.$path;
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base.'/'.$tenantId.$path;
    }

    /**
     * Waiting-room TV bookmark for Live Queue Control (Open screen / Copy link).
     *
     * On path tenancy (e.g. 127.0.0.1:8000/{tenant}/admin) this uses the
     * current request host and port — not the tenant's Domain row — so staff
     * are not sent to http://tenant.localhost/… on port 80 while artisan serve
     * is on :8000. Custom-domain tenants still use publicAbsolute().
     */
    public static function screenBookmarkUrl(string $tenantId, int $sessionId): string
    {
        $path = '/screen/'.$sessionId;

        if (static::usesPathPrefix()) {
            return url(static::url($path));
        }

        return static::publicAbsolute($tenantId, $path);
    }

    /**
     * Scheme for an outbound link on a known public host.
     *
     * Real clinic domains must not inherit `http` from a leftover
     * `APP_URL=http://localhost`. Local/dev `*.localhost` Domain rows stay
     * `http` so feature tests can hit them without TLS.
     */
    public static function schemeForOutboundHost(string $host): string
    {
        $host = strtolower($host);

        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.localhost')) {
            return 'http';
        }

        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $appHost = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: ''));

        if (
            $appHost === ''
            || $appHost === 'localhost'
            || $appHost === '127.0.0.1'
            || str_ends_with($appHost, '.localhost')
        ) {
            return 'https';
        }

        return $scheme === 'http' ? 'http' : 'https';
    }

    /**
     * First URL segments reserved on the central domain (marketing, super admin, assets).
     *
     * @return list<string>
     */
    public static function reservedPathPrefixes(): array
    {
        return config('tenancy.reserved_path_prefixes', []);
    }

    /**
     * Regex (without delimiters) for the `{tenant}` path parameter on central domains.
     */
    public static function tenantSlugPattern(): string
    {
        $reserved = implode('|', array_map(
            static fn (string $segment): string => preg_quote($segment, '/'),
            static::reservedPathPrefixes()
        ));

        // Group reserved alts before `$` so only exact segments are rejected
        // (e.g. `admin` blocked, `bookna` / `screening` allowed).
        return '(?!(?:'.$reserved.')$)[a-z0-9\-]+';
    }

    public static function usesPathPrefix(): bool
    {
        if (! tenancy()->initialized || ! app()->bound('request')) {
            return false;
        }

        return in_array(request()->getHost(), config('tenancy.central_domains', []), true);
    }

    public static function pathPrefix(): string
    {
        if (! static::usesPathPrefix()) {
            return '';
        }

        return '/'.tenant('id');
    }

    public static function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            $prefix = static::pathPrefix();

            return $prefix !== '' ? $prefix.'/' : '/';
        }

        return static::pathPrefix().$path;
    }

    public static function routeName(string $name): string
    {
        return static::usesPathPrefix() ? self::PATH_ROUTE_PREFIX.$name : $name;
    }

    /**
     * @param  array<string, mixed>|object  $parameters
     */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (! static::usesPathPrefix()) {
            return route($name, $parameters, $absolute);
        }

        $routeName = static::PATH_ROUTE_PREFIX.$name;

        if (! is_array($parameters)) {
            $parameters = static::normalizeRouteParameters($routeName, $parameters);
        }

        return route($routeName, array_merge(['tenant' => tenant('id')], $parameters), $absolute);
    }

    /**
     * Tenant-aware signed route — mirrors `route()` above so a signed URL built
     * under central-domain path tenancy points at the prefixed route name.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function temporarySignedRoute(
        string $name,
        \DateTimeInterface $expiration,
        array $parameters = [],
        bool $absolute = true,
    ): string {
        if (! static::usesPathPrefix()) {
            return URL::temporarySignedRoute($name, $expiration, $parameters, $absolute);
        }

        return URL::temporarySignedRoute(
            static::PATH_ROUTE_PREFIX.$name,
            $expiration,
            array_merge(['tenant' => tenant('id')], $parameters),
            $absolute,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRouteParameters(string $routeName, mixed $parameters): array
    {
        if ($parameters instanceof Model) {
            $route = app('router')->getRoutes()->getByName($routeName);

            if ($route) {
                $bindingName = collect($route->parameterNames())
                    ->reject(fn (string $parameter): bool => $parameter === 'tenant')
                    ->first();

                if ($bindingName) {
                    return [$bindingName => $parameters];
                }
            }
        }

        return is_array($parameters) ? $parameters : [$parameters];
    }
}
