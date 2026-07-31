<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresTenantAdminPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\SetPathTenantUrlDefaults;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

class TenantAdminPathPanelProvider extends PanelProvider
{
    use ConfiguresTenantAdminPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureTenantAdminPanel(
            $panel
                ->id('tenantAdminPath')
                ->path('{tenant}/admin')
                ->domains(config('tenancy.central_domains', []))
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
                    InitializeTenancyByPath::class,
                    SetPathTenantUrlDefaults::class,
                    DisableBladeIconComponents::class,
                    DispatchServingFilamentEvent::class,
                ], isPersistent: true)
                ->authMiddleware([
                    Authenticate::class,
                ])
        );
    }

    public function register(): void
    {
        if (blank(config('tenancy.central_domains'))) {
            return;
        }

        parent::register();
    }
}
