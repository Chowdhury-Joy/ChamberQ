<?php

namespace App\Http\Middleware;

use App\Support\TenancyUrl;
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
        if (tenancy()->initialized && TenancyUrl::usesPathPrefix()) {
            URL::defaults(['tenant' => tenant('id')]);
        }

        return $next($request);
    }
}
