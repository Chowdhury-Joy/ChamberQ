<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Marketer;
use App\Models\Tenant;
use App\Services\CommissionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformFinanceOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $finance = app(CommissionService::class)->platformFinanceSummary();
        $monthFinance = app(CommissionService::class)->platformFinanceSummary(now()->format('Y-m'));
        $referredActive = Tenant::query()
            ->whereNotNull('marketer_id')
            ->whereIn('billing_status', ['active', 'trial'])
            ->count();
        $topMarketer = Marketer::query()
            ->withCount('tenants')
            ->orderByDesc('tenants_count')
            ->first();

        return [
            Stat::make('Cash collected (all time)', '৳'.number_format($finance['collected']))
                ->description('This month: ৳'.number_format($monthFinance['collected']))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Commissions owed', '৳'.number_format($finance['owed']))
                ->description('Awaiting marketer payout')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Commissions paid out', '৳'.number_format($finance['paid']))
                ->description('Marked paid to marketers')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Net platform revenue', '৳'.number_format($finance['net']))
                ->description('Collected − commissions paid')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('amber'),

            Stat::make('Referred active tenants', (string) $referredActive)
                ->description($topMarketer
                    ? 'Top partner: '.$topMarketer->display_name.' ('.$topMarketer->tenants_count.')'
                    : 'No marketers yet')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('sky'),
        ];
    }
}
