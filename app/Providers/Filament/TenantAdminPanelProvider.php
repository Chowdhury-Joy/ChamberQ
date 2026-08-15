<?php

namespace App\Providers\Filament;

use App\Filament\TenantAdmin\Pages\Dashboard;
use App\Http\Middleware\Localization;
use App\Providers\Filament\Concerns\ConfiguresTenantAdminPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantAdminPanelProvider extends PanelProvider
{
    use ConfiguresTenantAdminPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureTenantAdminPanel(
            $panel
                ->id('tenantAdmin')
                ->path('admin')
                ->pages([
                    Dashboard::class,
                ])
                ->middleware([
                    EncryptCookies::class,
                    AddQueuedCookiesToResponse::class,
                    StartSession::class,
                    AuthenticateSession::class,
                    ShareErrorsFromSession::class,
                    VerifyCsrfToken::class,
                    SubstituteBindings::class,
                    InitializeTenancyByDomain::class,
                    PreventAccessFromCentralDomains::class,
                    // After session + tenancy: SetUpPanel (bootUsing) runs first
                    // and cannot see either, so locale must be applied here.
                    Localization::class,
                    DisableBladeIconComponents::class,
                    DispatchServingFilamentEvent::class,
                ], isPersistent: true)
                ->authMiddleware([
                    Authenticate::class,
                ])
        );
    }
}
