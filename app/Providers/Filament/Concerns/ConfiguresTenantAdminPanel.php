<?php

namespace App\Providers\Filament\Concerns;

use App\Filament\TenantAdmin\Widgets\TenantStatsOverview;
use App\Filament\TenantAdmin\Widgets\TodayAppointmentsWidget;
use App\Support\FilamentPanelUrl;
use Filament\Actions\Action;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\App;

trait ConfiguresTenantAdminPanel
{
    use UsesHamburgerSidebarToggle;

    protected function configureTenantAdminPanel(Panel $panel): Panel
    {
        return $this->withHamburgerSidebarToggle($panel)
            ->brandName(fn (): string => tenant()?->displayName() ?? 'ChamberQ')
            ->viteTheme('resources/css/filament/tenantAdmin/theme.css')
            ->homeUrl(fn (): string => FilamentPanelUrl::home())
            ->login()
            ->passwordReset()
            ->topbar(false)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                'Operations' => NavigationGroup::make()->label('Operations'),
                'HR' => NavigationGroup::make()->label('HR'),
                'Website' => NavigationGroup::make()->label('Website'),
                'Settings' => NavigationGroup::make()->label('Settings'),
            ])
            ->userMenuItems([
                Action::make('displayLanguage')
                    ->label(fn (): string => App::getLocale() === 'bn'
                        ? 'Switch to English'
                        : 'Switch to Bangla')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => tenant_web_url('/lang/'.(App::getLocale() === 'bn' ? 'en' : 'bn')))
                    ->visible(fn (): bool => tenancy()->initialized),
            ])
            ->discoverResources(in: app_path('Filament/TenantAdmin/Resources'), for: 'App\Filament\TenantAdmin\Resources')
            ->discoverPages(in: app_path('Filament/TenantAdmin/Pages'), for: 'App\Filament\TenantAdmin\Pages')
            ->discoverWidgets(in: app_path('Filament/TenantAdmin/Widgets'), for: 'App\Filament\TenantAdmin\Widgets')
            ->widgets([
                AccountWidget::class,
                TenantStatsOverview::class,
                TodayAppointmentsWidget::class,
            ])
            // Sidebar open on desktop by default; staff can still collapse via the hamburger.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => <<<'HTML'
                    <script>
                        (() => {
                            try {
                                localStorage.setItem('isOpen', JSON.stringify(true));
                                localStorage.setItem('isOpenDesktop', JSON.stringify(true));
                                localStorage.setItem('_x_isOpen', JSON.stringify(true));
                                localStorage.setItem('_x_isOpenDesktop', JSON.stringify(true));
                            } catch (e) {}
                        })();
                    </script>
                HTML
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.tenant-admin.components.offline-shell')->render()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => <<<'HTML'
                    <script>
                        document.addEventListener('alpine:initialized', () => {
                            try {
                                if (window.innerWidth < 1024) {
                                    return;
                                }
                                const sidebar = window.Alpine?.store?.('sidebar');
                                if (sidebar && ! sidebar.isOpen) {
                                    sidebar.open();
                                }
                            } catch (e) {}
                        }, { once: true });
                    </script>
                HTML
            )
            ->renderHook(
                'panels::head.end',
                fn (): string => (App::getLocale() === 'bn'
                    ? '<link rel="stylesheet" href="'.asset('css/chamberq-screen-fonts.css').'">'
                    : '').'<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'.'<style>
                    '.(App::getLocale() === 'bn' ? "
                    :root {
                        --fi-font-sans: 'Hind Siliguri', 'Geist Sans', ui-sans-serif, system-ui, sans-serif;
                    }
                    " : '').'
                    /* Collapse All / Expand All: gray outline, not primary */
                    .fi-fo-builder-header-actions,
                    .fi-fo-builder-header-actions button,
                    .fi-ac-action-btn {
                        font-weight: 700 !important;
                    }
                    /* Collapse All / Expand All stay gray, not primary */
                    .fi-fo-builder-header-actions button {
                        background-color: transparent !important;
                        color: #52525b !important;
                        padding: 6px 14px !important;
                        border-radius: 6px !important;
                        font-size: 0.85rem !important;
                        font-weight: 500 !important;
                        border: 1px solid #d4d4d8 !important;
                        box-shadow: none !important;
                        transition: all 0.15s ease !important;
                    }
                    .fi-fo-builder-header-actions button:hover {
                        background-color: #f4f4f5 !important;
                        color: #18181b !important;
                    }
                    /* High-visibility Add Section & Insert Between Buttons */
                    .fi-fo-builder-add-between-btn,
                    .fi-fo-builder-block-add-btn {
                        background-color: #0284c7 !important;
                        color: #ffffff !important;
                        font-weight: 800 !important;
                        border: 2px solid #0369a1 !important;
                        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4) !important;
                        border-radius: 9999px !important;
                        padding: 6px 16px !important;
                    }
                    /* Block Header Action Buttons (Copy, Hide, Delete) */
                    .fi-fo-builder-block-header-actions {
                        gap: 8px !important;
                        align-items: center !important;
                    }
                    .fi-fo-builder-block-header-actions button {
                        background-color: #f8fafc !important;
                        border: 1px solid #cbd5e1 !important;
                        border-radius: 8px !important;
                        padding: 4px 10px !important;
                        font-weight: 700 !important;
                        transition: all 0.2s ease !important;
                    }
                    .fi-fo-builder-block-header-actions button:hover {
                        background-color: #e2e8f0 !important;
                    }
                    /* Red Danger styling for Hidden block action toggle */
                    .fi-fo-builder-block-header-actions button.fi-color-danger,
                    .fi-fo-builder-block-header-actions button[class*="danger"] {
                        background-color: #fef2f2 !important;
                        border-color: #fca5a5 !important;
                        color: #dc2626 !important;
                    }
                    .fi-fo-builder-block-header-actions button.fi-color-danger svg,
                    .fi-fo-builder-block-header-actions button[class*="danger"] svg {
                        color: #dc2626 !important;
                    }
                </style>'
            )
            ->bootUsing(function (): void {
                if (tenancy()->initialized) {
                    if (session()->has('locale')) {
                        App::setLocale((string) session()->get('locale'));
                    } elseif (filled(tenant()->default_locale)) {
                        App::setLocale((string) tenant()->default_locale);
                    }
                }

                Table::configureUsing(function (Table $table): void {
                    $table->modifyUngroupedRecordActionsUsing(
                        fn (Action $action): Action => $action->button()->outlined(),
                    );
                });
            });
    }
}
