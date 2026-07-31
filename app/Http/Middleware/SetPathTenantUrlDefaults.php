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
            $tenant = tenant('id');
        } elseif ($request->hasSession() && $request->session()->has('filament_path_tenant')) {
            URL::defaults(['tenant' => $request->session()->get('filament_path_tenant')]);
            return $next($request);
        }

        if ($request->hasSession() && is_string($tenant) && $tenant !== '') {
            $request->session()->put('filament_path_tenant', $tenant);
        }

        return $next($request);
    }
}
