<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OperationalReportService
{
    public const TIMEZONE = 'Asia/Dhaka';

    /** @var list<string> */
    public const STATUSES = [
        'completed',
        'waiting',
        'called',
        'in_chamber',
        'skipped',
        'no_show',
        'cancelled',
    ];

    /**
     * @return array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}
     */
    public function emptyCounts(): array
    {
        $counts = ['total' => 0];

        foreach (self::STATUSES as $status) {
            $counts[$status] = 0;
        }

        return $counts;
    }

    /**
     * Inclusive date range on booking_date (calendar dates in Asia/Dhaka).
     *
     * @return array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}
     */
    public function countsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Booking::query()
            ->whereDate('booking_date', '>=', $from->toDateString())
            ->whereDate('booking_date', '<=', $to->toDateString())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = $this->emptyCounts();

        foreach ($rows as $status => $count) {
            $counts[$status] = (int) $count;
            $counts['total'] += (int) $count;
        }

        return $counts;
    }

    /**
     * @return array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}
     */
    public function countsForDate(CarbonInterface $date): array
    {
        $day = $this->inClinicTz($date)->startOfDay();

        return $this->countsBetween($day, $day);
    }

    /**
     * Week containing $anchor, Sunday–Saturday in Asia/Dhaka.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function weekRange(CarbonInterface $anchor): array
    {
        $day = $this->inClinicTz($anchor);

        return [
            $day->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay(),
            $day->copy()->endOfWeek(Carbon::SATURDAY)->startOfDay(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function monthRange(CarbonInterface $anchor): array
    {
        $day = $this->inClinicTz($anchor);

        return [
            $day->copy()->startOfMonth()->startOfDay(),
            $day->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * One row per calendar day in the inclusive range (zeros for empty days).
     *
     * @return array<string, array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}>
     */
    public function dailyBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDay = $this->inClinicTz($from)->startOfDay();
        $toDay = $this->inClinicTz($to)->startOfDay();

        $rows = Booking::query()
            ->whereDate('booking_date', '>=', $fromDay->toDateString())
            ->whereDate('booking_date', '<=', $toDay->toDateString())
            ->selectRaw('booking_date, status, COUNT(*) as aggregate')
            ->groupBy('booking_date', 'status')
            ->get();

        $byDay = [];
        $cursor = $fromDay->copy();

        while ($cursor->lte($toDay)) {
            $byDay[$cursor->toDateString()] = $this->emptyCounts();
            $cursor->addDay();
        }

        foreach ($rows as $row) {
            $date = Carbon::parse($row->booking_date)->toDateString();

            if (! isset($byDay[$date])) {
                $byDay[$date] = $this->emptyCounts();
            }

            $byDay[$date][$row->status] = (int) $row->aggregate;
            $byDay[$date]['total'] += (int) $row->aggregate;
        }

        return $byDay;
    }

    /**
     * Weeks that intersect the month of $anchor (Sunday–Saturday), clipped to month totals.
     *
     * @return list<array{week_start: string, week_end: string, total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}>
     */
    public function weeklyBreakdownInMonth(CarbonInterface $anchor): array
    {
        [$monthStart, $monthEnd] = $this->monthRange($anchor);
        $daily = $this->dailyBreakdown($monthStart, $monthEnd);
        $byWeek = [];

        foreach ($daily as $date => $counts) {
            $day = Carbon::parse($date, self::TIMEZONE);
            $weekStart = $day->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();
            $weekEnd = $day->copy()->endOfWeek(Carbon::SATURDAY)->toDateString();

            if (! isset($byWeek[$weekStart])) {
                $byWeek[$weekStart] = array_merge($this->emptyCounts(), [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                ]);
            }

            foreach (self::STATUSES as $status) {
                $byWeek[$weekStart][$status] += $counts[$status];
            }

            $byWeek[$weekStart]['total'] += $counts['total'];
        }

        return array_values($byWeek);
    }

    protected function inClinicTz(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateString(), self::TIMEZONE);
    }
}
