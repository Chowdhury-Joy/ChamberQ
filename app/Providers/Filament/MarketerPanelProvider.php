<?php

namespace App\Providers\Filament;

use App\Filament\Marketer\Widgets\MarketerStatsOverview;
use App\Filament\Marketer\Widgets\ReferralLinkWidget;
use App\Providers\Filament\Concerns\UsesHamburgerSidebarToggle;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MarketerPanelProvider extends PanelProvider
{
    use UsesHamburgerSidebarToggle;

    public function panel(Panel $panel): Panel
    {
        return $this->withHamburgerSidebarToggle($panel)
            ->id('marketer')
            ->path('partner')
            ->brandName('ChamberQ')
            ->login()
            ->passwordReset()
            ->domains(config('tenancy.central_domains') ?? [])
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Marketer/Resources'), for: 'App\Filament\Marketer\Resources')
            ->discoverPages(in: app_path('Filament/Marketer/Pages'), for: 'App\Filament\Marketer\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Marketer/Widgets'), for: 'App\Filament\Marketer\Widgets')
            ->widgets([
                AccountWidget::class,
                ReferralLinkWidget::class,
                MarketerStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
