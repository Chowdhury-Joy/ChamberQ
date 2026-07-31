<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Filament\Concerns\UsesCardGridColumns;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use App\Scopes\TenantScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperAdminStatsOverview extends BaseWidget
{
    use UsesCardGridColumns;
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $clinicTenants = Tenant::where('plan_tier', 'clinic')->count();
        $soloTenants = Tenant::where('plan_tier', 'solo')->count();
        // Super Admin runs on central domains without initialized tenancy — aggregate
        // across all tenants by bypassing the tenant scope (never leak in tenant panels).
        $totalBookings = Booking::withoutGlobalScope(TenantScope::class)->count();
        $totalDoctors = Doctor::withoutGlobalScope(TenantScope::class)->count();
        $publishedPages = WebPage::withoutGlobalScope(TenantScope::class)
            ->where('is_published', true)
            ->count();

        return [
            Stat::make('Total Platform Tenants', $totalTenants)
                ->description("Clinics: {$clinicTenants} | Solo: {$soloTenants}")
                ->descriptionIcon('heroicon-m-building-library')
                ->color('amber'),

            Stat::make('Total Platform Bookings', $totalBookings)
                ->description('Appointments booked across all tenants')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),

            Stat::make('Registered Doctors', $totalDoctors)
                ->description('Practicing physicians on platform')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Published Web Pages', $publishedPages)
                ->description('Custom landing & policy web pages')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('sky'),
        ];
    }
}
