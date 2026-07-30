<?php

namespace App\Filament\Marketer\Widgets;

use App\Models\Commission;
use App\Models\Marketer;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketerStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $marketer = $this->marketer();
        if (! $marketer) {
            return [];
        }

        $owed = (int) Commission::query()
            ->where('marketer_id', $marketer->id)
            ->where('status', Commission::STATUS_OWED)
            ->sum('commission_amount');

        $paid = (int) Commission::query()
            ->where('marketer_id', $marketer->id)
            ->where('status', Commission::STATUS_PAID)
            ->sum('commission_amount');

        $referred = Tenant::query()->where('marketer_id', $marketer->id)->count();

        $pendingThisMonth = (int) Commission::query()
            ->where('marketer_id', $marketer->id)
            ->where('status', Commission::STATUS_PENDING)
            ->where('period', now()->format('Y-m'))
            ->sum('commission_amount');

        return [
            Stat::make('Owed to you', '৳'.number_format($owed))
                ->description('Confirmed doctor payments, awaiting your payout')
                ->color('warning'),

            Stat::make('Paid to you', '৳'.number_format($paid))
                ->description('All-time payouts marked paid')
                ->color('success'),

            Stat::make('Referred doctors', (string) $referred)
                ->description('Tenants linked to your referral')
                ->color('info'),

            Stat::make('This month pending', '৳'.number_format($pendingThisMonth))
                ->description('Waiting for doctor payment confirmation')
                ->color('gray'),
        ];
    }

    private function marketer(): ?Marketer
    {
        return auth()->user()?->marketerProfile;
    }
}
