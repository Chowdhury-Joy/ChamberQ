<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Where a Filament panel's "home" actually is.
 *
 * `Panel::getUrl()` resolves a `home` route that Filament never registers, so it
 * always falls through to `url($panel->getPath())`. For the two central panels
 * that is harmless — their paths are literally `admin` and `partner`. For the
 * path tenant panel the path is the *pattern* `{tenant}/admin`, so the fallback
 * hands out `/%7Btenant%7D/admin` and the browser 404s.
 *
 * Resolving the panel's own dashboard route instead fixes it for every panel.
 * `generateRouteName()` is Filament's own helper, so the domain segment that
 * multi-domain panels put in their route names stays correct.
 */
class FilamentPanelUrl
{
    public static function home(?Panel $panel = null, ?string $tenant = null): string
    {
        $panel ??= Filament::getCurrentOrDefaultPanel();

        $routeName = $panel->generateRouteName('pages.dashboard');

        if (! Route::has($routeName)) {
            return $panel->getUrl();
        }

        $tenant ??= self::currentTenantSlug();

        return route($routeName, $tenant !== null ? ['tenant' => $tenant] : []);
    }

    private static function currentTenantSlug(): ?string
    {
        $routeTenant = request()->route('tenant');

        if (is_string($routeTenant) && $routeTenant !== '') {
            return $routeTenant;
        }

        if (tenancy()->initialized) {
            return (string) tenant('id');
        }

        return null;
    }
}
