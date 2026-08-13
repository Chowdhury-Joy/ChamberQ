<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks public/staff routes when the tenant has not bought that product module.
 *
 * Apply per-route with the module key (front_door | live_queue | prescription).
 * Missing flag = module on (see Tenant::hasFeature), so existing chambers stay open.
 */
class EnsureTenantHasModule
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (tenancy()->initialized && tenant() && ! tenant()->hasFeature($module)) {
            abort(404);
        }

        return $next($request);
    }
}
