<?php

namespace App\Providers\Filament;

use App\Filament\SuperAdmin\Widgets\PlatformFinanceOverview;
use App\Filament\SuperAdmin\Widgets\RecentTenantsWidget;
use App\Filament\SuperAdmin\Widgets\SuperAdminStatsOverview;
use App\Providers\Filament\Concerns\UsesHamburgerSidebarToggle;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    use UsesHamburgerSidebarToggle;

    public function panel(Panel $panel): Panel
    {
        return $this->withHamburgerSidebarToggle($panel)
            ->id('superAdmin')
            ->path('admin')
            ->brandName('ChamberQ')
            ->viteTheme('resources/css/filament/tenantAdmin/theme.css')
            ->login()
            ->passwordReset()
            ->topbar(false)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->domains(config('tenancy.central_domains') ?? [])
            ->colors([
                'primary' => Color::Blue,
                'amber' => Color::Amber,
                'sky' => Color::Sky,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">',
            )
            ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\Filament\SuperAdmin\Resources')
            ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\Filament\SuperAdmin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\Filament\SuperAdmin\Widgets')
            ->widgets([
                PlatformFinanceOverview::class,
                SuperAdminStatsOverview::class,
                RecentTenantsWidget::class,
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
            ])
            ->bootUsing(function (): void {
                Table::configureUsing(function (Table $table): void {
                    $table->modifyUngroupedRecordActionsUsing(
                        fn (Action $action): Action => $action->button()->outlined(),
                    );
                });
            });
    }
}
