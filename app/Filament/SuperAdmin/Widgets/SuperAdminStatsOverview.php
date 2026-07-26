<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperAdminStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $clinicTenants = Tenant::where('plan_tier', 'clinic')->count();
        $soloTenants = Tenant::where('plan_tier', 'solo')->count();
        $totalBookings = Booking::count();
        $totalDoctors = Doctor::count();
        $publishedPages = WebPage::where('is_published', true)->count();

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
