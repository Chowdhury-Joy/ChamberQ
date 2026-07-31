<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament path panels ({tenant}/admin) need the tenant slug in generated URLs.
 */
class SetPathTenantUrlDefaults
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return $next($request);
        }

        $tenant = $request->route('tenant');

        if (is_string($tenant) && $tenant !== '') {
            URL::defaults(['tenant' => $tenant]);
        } elseif (tenancy()->initialized) {
            URL::defaults(['tenant' => tenant('id')]);
        }

        return $next($request);
    }
}
