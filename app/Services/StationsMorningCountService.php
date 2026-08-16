<?php

namespace App\Services;

use App\Jobs\SendStationsMorningCountPushes;
use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;

class StationsMorningCountService
{
    public function dispatchForAllTenants(): int
    {
        $count = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use (&$count): void {
            if (! $tenant->hasStations()) {
                return;
            }

            tenancy()->initialize($tenant);

            try {
                $summary = $this->summaryForToday();
                if ($summary === null) {
                    return;
                }

                SendStationsMorningCountPushes::dispatch($tenant->id, $summary);
                $count++;
            } finally {
                tenancy()->end();
            }
        });

        return $count;
    }

    /**
     * @return array{visit_waiting: int, intervention_waiting: int, date: string}|null
     */
    public function summaryForToday(): ?array
    {
        $today = Carbon::today()->toDateString();
        $dayOfWeek = Carbon::today()->dayOfWeek;

        $sessionIdsByKind = ScheduleSession::query()
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('kind', [ScheduleSession::KIND_VISIT, ScheduleSession::KIND_INTERVENTION])
            ->get()
            ->groupBy('kind')
            ->map(fn ($rows) => $rows->pluck('id')->all());

        if ($sessionIdsByKind->isEmpty()) {
            return null;
        }

        $visitIds = $sessionIdsByKind->get(ScheduleSession::KIND_VISIT, []);
        $interventionIds = $sessionIdsByKind->get(ScheduleSession::KIND_INTERVENTION, []);

        $visitWaiting = $visitIds === [] ? 0 : Booking::query()
            ->where('booking_date', $today)
            ->where('bookable_type', ScheduleSession::class)
            ->whereIn('bookable_id', $visitIds)
            ->where('status', 'waiting')
            ->count();

        $interventionWaiting = $interventionIds === [] ? 0 : Booking::query()
            ->where('booking_date', $today)
            ->where('bookable_type', ScheduleSession::class)
            ->whereIn('bookable_id', $interventionIds)
            ->where('status', 'waiting')
            ->count();

        if ($visitWaiting === 0 && $interventionWaiting === 0) {
            return null;
        }

        return [
            'visit_waiting' => $visitWaiting,
            'intervention_waiting' => $interventionWaiting,
            'date' => $today,
        ];
    }
}
