<?php

namespace App\Filament\TenantAdmin\Widgets;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LabTest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class TenantStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $tenant = tenant();
        $today = Carbon::today()->toDateString();
        
        $todayBookingsCount = Booking::whereDate('booking_date', $today)->count();
        $totalBookingsCount = Booking::count();
        $doctorsCount = Doctor::count();
        $chambersCount = Chamber::count();

        $stats = [
            Stat::make("Today's Appointments", $todayBookingsCount)
                ->description('Bookings scheduled for today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('sky'),

            Stat::make('Total Appointments', $totalBookingsCount)
                ->description('All-time patient bookings')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),

            Stat::make('Active Chambers', $chambersCount)
                ->description('Configured consultation locations')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info'),
        ];

        if ($tenant?->isClinic()) {
            $stats[] = Stat::make('Medical Specialists', $doctorsCount)
                ->description('Registered doctors in clinic')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning');

            $stats[] = Stat::make('Lab Services', LabTest::where('is_active', true)->count())
                ->description('Active diagnostic lab tests')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('primary');
        }

        return $stats;
    }
}
