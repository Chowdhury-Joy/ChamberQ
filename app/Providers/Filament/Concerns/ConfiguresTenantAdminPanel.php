<?php

namespace App\Providers\Filament\Concerns;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;

trait ConfiguresTenantAdminPanel
{
    protected function configureTenantAdminPanel(Panel $panel): Panel
    {
        return $panel
            ->brandName('ChamberQ')
            ->viteTheme('resources/css/filament/tenantAdmin/theme.css')
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/TenantAdmin/Resources'), for: 'App\Filament\TenantAdmin\Resources')
            ->discoverPages(in: app_path('Filament/TenantAdmin/Pages'), for: 'App\Filament\TenantAdmin\Pages')
            ->discoverWidgets(in: app_path('Filament/TenantAdmin/Widgets'), for: 'App\Filament\TenantAdmin\Widgets')
            ->widgets([
                AccountWidget::class,
                \App\Filament\TenantAdmin\Widgets\TenantStatsOverview::class,
                \App\Filament\TenantAdmin\Widgets\TodayAppointmentsWidget::class,
            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => '<style>
                    /* High-visibility Collapse All / Expand All Action Pill Buttons */
                    .fi-fo-builder-header-actions,
                    .fi-fo-builder-header-actions button,
                    .fi-ac-action-btn {
                        font-weight: 700 !important;
                    }
                    .fi-fo-builder-header-actions button {
                        background-color: transparent !important;
                        color: #000000 !important;
                        padding: 6px 14px !important;
                        border-radius: 6px !important;
                        font-size: 0.85rem !important;
                        font-weight: 600 !important;
                        border: 1px solid #000000 !important;
                        box-shadow: none !important;
                        transition: all 0.15s ease !important;
                    }
                    .fi-fo-builder-header-actions button:hover {
                        background-color: #f4f4f5 !important;
                        color: #000000 !important;
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
            );
    }
}
