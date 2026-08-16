<?php

namespace App\Support;

use App\Models\User;
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
 * Resolving a named panel page instead fixes it for every panel.
 * `generateRouteName()` is Filament's own helper, so the domain segment that
 * multi-domain panels put in their route names stays correct.
 *
 * Doctors with the Prescription module land on Consult Screen — that is their
 * working surface, not the stats dashboard. Staff and the account owner still
 * land on the dashboard. Super Admin and Marketer are unchanged.
 */
class FilamentPanelUrl
{
    /** @var list<string> */
    private const TENANT_ADMIN_PANEL_IDS = ['tenantAdmin', 'tenantAdminPath'];

    public static function home(?Panel $panel = null, ?string $tenant = null): string
    {
        $panel ??= Filament::getCurrentOrDefaultPanel();
        $tenant ??= self::currentTenantSlug();

        $consult = self::consultScreen($panel, $tenant);
        if ($consult !== null) {
            return $consult;
        }

        return self::pageUrl($panel, 'pages.dashboard', $tenant) ?? $panel->getUrl();
    }

    /**
     * Consult Screen URL when this login should open there; otherwise null.
     */
    public static function consultScreen(?Panel $panel = null, ?string $tenant = null): ?string
    {
        $panel ??= Filament::getCurrentOrDefaultPanel();

        if (! self::doctorLandsOnConsult($panel)) {
            return null;
        }

        $tenant ??= self::currentTenantSlug();

        return self::pageUrl($panel, 'pages.consult-screen', $tenant);
    }

    private static function doctorLandsOnConsult(Panel $panel): bool
    {
        if (! in_array($panel->getId(), self::TENANT_ADMIN_PANEL_IDS, true)) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->landsOnConsultScreen();
    }

    private static function pageUrl(Panel $panel, string $relativeName, ?string $tenant): ?string
    {
        $routeName = $panel->generateRouteName($relativeName);

        if (! Route::has($routeName)) {
            return null;
        }

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
