<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\WebPage;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Super Admin seller overview — tenant-level counts only, never patient contents.
 *
 * @see patient-records-plan.md Part 5
 */
class SellerOverviewService
{
    public const SMS_LOW_THRESHOLD = 5;

    public const RECENT_SIGNUP_DAYS = 90;

    public const BASELINE_WEEKS = 4;

    public const QUIET_SESSION_DAYS = 7;

    public const BOOKING_DROP_THRESHOLD = 50;

    public function quietClients(?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $weekStart = $asOf->copy()->startOfWeek();
        $weekEnd = $asOf->copy()->endOfWeek();
        $baselineStart = $weekStart->copy()->subWeeks(self::BASELINE_WEEKS);
        $baselineEnd = $weekStart->copy()->subDay();

        $lastSessions = LiveSession::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('started_at')
            ->selectRaw('tenant_id, MAX(session_date) as last_session_date')
            ->groupBy('tenant_id')
            ->pluck('last_session_date', 'tenant_id');

        $bookingsThisWeek = Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $bookingsBaseline = Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('booking_date', [$baselineStart->toDateString(), $baselineEnd->toDateString()])
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $scheduleTenants = ScheduleSession::withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $startedLiveTenants = LiveSession::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('started_at')
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $tenants = Tenant::query()
            ->whereIn('billing_status', ['trial', 'active', 'past_due'])
            ->get(['id', 'name', 'created_at']);

        $rows = $tenants->map(function (Tenant $tenant) use (
            $asOf,
            $lastSessions,
            $bookingsThisWeek,
            $bookingsBaseline,
            $scheduleTenants,
            $startedLiveTenants,
        ) {
            $lastSessionDate = $lastSessions->get($tenant->id);
            $daysSinceLastSession = $lastSessionDate
                ? (int) Carbon::parse($lastSessionDate)->diffInDays($asOf)
                : null;

            $thisWeek = (int) ($bookingsThisWeek->get($tenant->id) ?? 0);
            $baselineTotal = (int) ($bookingsBaseline->get($tenant->id) ?? 0);
            $baselineWeekly = self::BASELINE_WEEKS > 0
                ? round($baselineTotal / self::BASELINE_WEEKS, 1)
                : 0.0;

            $bookingDropPercent = null;
            if ($baselineWeekly > 0) {
                $bookingDropPercent = (int) round(
                    max(0, ($baselineWeekly - $thisWeek) / $baselineWeekly * 100)
                );
            }

            $hasSchedule = ((int) ($scheduleTenants->get($tenant->id) ?? 0)) > 0;
            $hasStartedLive = ((int) ($startedLiveTenants->get($tenant->id) ?? 0)) > 0;
            $scheduledNeverStarted = $hasSchedule && ! $hasStartedLive;

            $isQuiet = $this->tenantLooksQuiet(
                $tenant,
                $daysSinceLastSession,
                $bookingDropPercent,
                $scheduledNeverStarted,
            );

            if (! $isQuiet) {
                return null;
            }

            return [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->displayName(),
                'days_since_last_session' => $daysSinceLastSession,
                'bookings_this_week' => $thisWeek,
                'bookings_baseline_weekly' => $baselineWeekly,
                'booking_drop_percent' => $bookingDropPercent,
                'scheduled_never_started' => $scheduledNeverStarted,
                'sort_days' => $daysSinceLastSession ?? 9999,
                'sort_drop' => $bookingDropPercent ?? 0,
            ];
        })->filter()->values();

        return $rows
            ->sortByDesc(fn (array $row) => $row['sort_days'] * 1000 + $row['sort_drop'] + ($row['scheduled_never_started'] ? 500 : 0))
            ->values()
            ->map(function (array $row) {
                unset($row['sort_days'], $row['sort_drop']);

                return $row;
            });
    }

    public function goLiveFunnel(?int $recentDays = null, ?Carbon $asOf = null): Collection
    {
        $recentDays = $recentDays ?? self::RECENT_SIGNUP_DAYS;
        $asOf = $asOf ?? now();
        $cutoff = $asOf->copy()->subDays($recentDays);

        $chamberCounts = Chamber::withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $scheduleCounts = ScheduleSession::withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $publishedSites = WebPage::withoutGlobalScope(TenantScope::class)
            ->where('is_published', true)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $firstBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, MIN(booking_date) as first_booking_date')
            ->groupBy('tenant_id')
            ->pluck('first_booking_date', 'tenant_id');

        $firstLiveSessions = LiveSession::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('started_at')
            ->selectRaw('tenant_id, MIN(started_at) as first_started_at')
            ->groupBy('tenant_id')
            ->pluck('first_started_at', 'tenant_id');

        $steps = [
            'account_made',
            'chambers_added',
            'schedule_set',
            'website_live',
            'first_booking',
            'first_live_session',
        ];

        return Tenant::query()
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'created_at'])
            ->map(function (Tenant $tenant) use (
                $chamberCounts,
                $scheduleCounts,
                $publishedSites,
                $firstBookings,
                $firstLiveSessions,
                $steps,
            ) {
                $completed = [
                    'account_made' => true,
                    'chambers_added' => ((int) ($chamberCounts->get($tenant->id) ?? 0)) > 0,
                    'schedule_set' => ((int) ($scheduleCounts->get($tenant->id) ?? 0)) > 0,
                    'website_live' => ((int) ($publishedSites->get($tenant->id) ?? 0)) > 0,
                    'first_booking' => $firstBookings->has($tenant->id),
                    'first_live_session' => $firstLiveSessions->has($tenant->id),
                ];

                $stallStep = 'first_live_session';
                foreach ($steps as $step) {
                    if (! $completed[$step]) {
                        $stallStep = $step;
                        break;
                    }
                    $stallStep = null;
                }

                $completedCount = collect($completed)->filter()->count();

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->displayName(),
                    'signed_up_at' => $tenant->created_at,
                    'steps' => $completed,
                    'completed_count' => $completedCount,
                    'stall_step' => $stallStep,
                    'is_live' => $completed['first_live_session'],
                ];
            });
    }

    public function smsCreditWarnings(?int $threshold = null): Collection
    {
        $threshold = $threshold ?? self::SMS_LOW_THRESHOLD;

        return Tenant::query()
            ->where('sms_balance', '<=', $threshold)
            ->whereIn('billing_status', ['trial', 'active', 'past_due'])
            ->orderBy('sms_balance')
            ->orderBy('name')
            ->get(['id', 'name', 'sms_balance', 'billing_status'])
            ->map(fn (Tenant $tenant) => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->displayName(),
                'sms_balance' => (int) $tenant->sms_balance,
                'billing_status' => $tenant->billing_status,
                'is_empty' => (int) $tenant->sms_balance <= 0,
            ]);
    }

    public function overduePayments(?Carbon $asOf = null): Collection
    {
        $asOf = $asOf ?? now();
        $currentPeriod = $asOf->format('Y-m');

        $lastMonthlyPayments = BillingPayment::query()
            ->where('type', BillingPayment::TYPE_MONTHLY)
            ->whereNotNull('confirmed_at')
            ->selectRaw('tenant_id, MAX(period) as last_period')
            ->groupBy('tenant_id')
            ->pluck('last_period', 'tenant_id');

        return Tenant::query()
            ->where(function ($query) {
                $query->whereIn('billing_status', ['past_due', 'suspended'])
                    ->orWhere(function ($inner) {
                        $inner->whereNull('setup_paid_at')
                            ->where('billing_status', '!=', 'trial');
                    });
            })
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) use ($asOf, $currentPeriod, $lastMonthlyPayments) {
                if (! $tenant->hasSetupPaid()) {
                    $overdueSince = $tenant->created_at->copy()->startOfDay();
                    $reason = 'setup_unpaid';

                    return [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->displayName(),
                        'reason' => $reason,
                        'days_overdue' => (int) $overdueSince->diffInDays($asOf->copy()->startOfDay()),
                        'billing_status' => $tenant->billing_status,
                    ];
                }

                $lastPeriod = $lastMonthlyPayments->get($tenant->id);
                if ($lastPeriod === $currentPeriod) {
                    return null;
                }

                if ($lastPeriod) {
                    $overdueSince = Carbon::createFromFormat('Y-m', $lastPeriod)->addMonth()->startOfMonth();
                } else {
                    $overdueSince = ($tenant->setup_paid_at ?? $tenant->created_at)->copy()->addMonth()->startOfMonth();
                }

                if ($overdueSince->greaterThan($asOf)) {
                    return null;
                }

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->displayName(),
                    'reason' => in_array($tenant->billing_status, ['past_due', 'suspended'], true)
                        ? 'billing_'.$tenant->billing_status
                        : 'monthly_missing',
                    'days_overdue' => (int) $overdueSince->diffInDays($asOf->copy()->startOfDay()),
                    'billing_status' => $tenant->billing_status,
                ];
            })
            ->filter()
            ->sortByDesc('days_overdue')
            ->values();
    }

    private function tenantLooksQuiet(
        Tenant $tenant,
        ?int $daysSinceLastSession,
        ?int $bookingDropPercent,
        bool $scheduledNeverStarted,
    ): bool {
        if ($tenant->created_at->greaterThan(now()->subDays(3))) {
            return false;
        }

        if ($scheduledNeverStarted && $tenant->created_at->lessThan(now()->subDays(7))) {
            return true;
        }

        if ($daysSinceLastSession !== null && $daysSinceLastSession >= self::QUIET_SESSION_DAYS) {
            return true;
        }

        if ($bookingDropPercent !== null && $bookingDropPercent >= self::BOOKING_DROP_THRESHOLD) {
            return true;
        }

        return false;
    }
}
