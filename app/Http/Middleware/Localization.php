<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Localization
{
    public function handle(Request $request, Closure $next)
    {
        // Priority: session override → tenant default → English.
        if (session()->has('locale')) {
            App::setLocale(session()->get('locale'));
        } elseif (tenancy()->initialized && filled(tenant()->default_locale)) {
            App::setLocale(tenant()->default_locale);
        } else {
            App::setLocale('en');
        }

        return $next($request);
    }
}
