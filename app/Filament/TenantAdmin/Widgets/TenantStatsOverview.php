<?php

namespace App\Filament\TenantAdmin\Widgets;

use App\Filament\Concerns\UsesCardGridColumns;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LabTest;
use App\Models\ScheduleSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class TenantStatsOverview extends BaseWidget
{
    use UsesCardGridColumns;
    protected function getStats(): array
    {
        $tenant = tenant();
        $today = Carbon::today()->toDateString();
        
        $todayBookingsCount = Booking::where('booking_date', $today)->count();
        $totalBookingsCount = Booking::count();
        $doctorsCount = Doctor::count();
        $chambersCount = ScheduleSession::query()->distinct()->count('chamber_id');

        $stats = [
            Stat::make(__("Today's Appointments"), $todayBookingsCount)
                ->description(__('Bookings scheduled for today'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('sky'),

            Stat::make(__('Total Appointments'), $totalBookingsCount)
                ->description(__('All-time patient bookings'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),

            Stat::make(__('SMS Credits'), (int) ($tenant?->sms_balance ?? 0))
                ->description(__('Prepaid confirmation balance — ask us to top up'))
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color(((int) ($tenant?->sms_balance ?? 0)) > 0 ? 'success' : 'warning'),

            Stat::make(__('Active Chambers'), $chambersCount)
                ->description(__('Chambers with scheduled sessions'))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info'),
        ];

        if ($tenant?->isClinic()) {
            $stats[] = Stat::make(__('Medical Specialists'), $doctorsCount)
                ->description(__('Registered doctors in clinic'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning');

            $stats[] = Stat::make(__('Lab Services'), LabTest::where('is_active', true)->count())
                ->description(__('Active diagnostic lab tests'))
                ->descriptionIcon('heroicon-m-beaker')
                ->color('primary');
        }

        return $stats;
    }
}
