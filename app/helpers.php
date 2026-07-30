<?php

use App\Support\TenancyUrl;

if (! function_exists('tenant_web_url')) {
    function tenant_web_url(string $path = '/'): string
    {
        return TenancyUrl::url($path);
    }
}

if (! function_exists('tenant_web_route')) {
    /**
     * Tenant-aware named routes (path prefix on central domain, root on custom domains).
     * Not to be confused with stancl/tenancy's tenant_route($domain, $route, ...).
     *
     * @param  array<string, mixed>|object  $parameters
     */
    function tenant_web_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return TenancyUrl::route($name, $parameters, $absolute);
    }
}
