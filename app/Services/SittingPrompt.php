<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One brain for overdue / in-delay / delay-expired / idle-after-start sitting prompts.
 *
 * Used by Daily Roster, Live Queue Control, and Consult Screen so wording
 * cannot drift between pages.
 */
class SittingPrompt
{
    public const GRACE_MINUTES = 10;

    /** @var list<int> */
    public const DELAY_OPTIONS = [15, 30, 45, 60, 90, 120];

    /**
     * @return Collection<int, array{
     *     kind: string,
     *     session_id: int,
     *     session_name: string,
     *     schedule_session_id: int,
     *     live_session_id: int|null,
     *     minutes_late: int,
     *     waiting_count: int,
     *     delay_minutes: int,
     *     suggested_delay_minutes: int|null,
     *     minutes_until_announced: int|null,
     *     minutes_past_announced: int|null,
     *     announced_at: Carbon|null,
     *     message: string,
     * }>
     */
    public function promptsForToday(?Carbon $now = null): Collection
    {
        $now ??= now();
        $todayDow = $now->dayOfWeek;
        $todayDate = $now->toDateString();

        $sessions = ScheduleSession::query()
            ->where('day_of_week', $todayDow)
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            return collect();
        }

        $liveBySession = LiveSession::query()
            ->whereIn('schedule_session_id', $sessions->pluck('id'))
            ->where('session_date', $todayDate)
            ->get()
            ->keyBy('schedule_session_id');

        return $sessions
            ->map(fn (ScheduleSession $session) => $this->promptForSession(
                $session,
                $liveBySession->get($session->id),
                $now,
            ))
            ->filter()
            ->values()
            ->tap(fn (Collection $prompts) => app(StaffSittingBuzzService::class)->dispatchForPrompts($prompts));
    }

    /**
     * @return array{
     *     kind: string,
     *     session_id: int,
     *     session_name: string,
     *     schedule_session_id: int,
     *     live_session_id: int|null,
     *     minutes_late: int,
     *     waiting_count: int,
     *     delay_minutes: int,
     *     suggested_delay_minutes: int|null,
     *     minutes_until_announced: int|null,
     *     minutes_past_announced: int|null,
     *     announced_at: Carbon|null,
     *     message: string,
     * }|null
     */
    public function promptForSession(
        ScheduleSession $session,
        ?LiveSession $live,
        ?Carbon $now = null,
    ): ?array {
        $now ??= now();

        if ($live && $live->status === 'active') {
            return $this->idleAfterStartPrompt($session, $live, $now);
        }

        if ($live && in_array($live->status, ['paused', 'completed', 'cancelled'], true)) {
            return null;
        }

        $waitingCount = $this->waitingCount($session, $now);
        if ($waitingCount < 1) {
            return null;
        }

        $sittingStart = $this->sittingStart($session, $now);

        if ($live && $live->status === 'delayed') {
            return $this->delayedPrompt($session, $live, $sittingStart, $waitingCount, $now);
        }

        if ($now->lt($sittingStart->copy()->addMinutes(self::GRACE_MINUTES))) {
            return null;
        }

        $minutesLate = (int) $sittingStart->diffInMinutes($now);

        return [
            'kind' => 'overdue',
            'session_id' => $session->id,
            'session_name' => $session->session_name,
            'schedule_session_id' => $session->id,
            'live_session_id' => $live?->id,
            'minutes_late' => $minutesLate,
            'waiting_count' => $waitingCount,
            'delay_minutes' => 0,
            'suggested_delay_minutes' => $this->suggestedDelayMinutes($minutesLate),
            'minutes_until_announced' => null,
            'minutes_past_announced' => null,
            'announced_at' => null,
            'message' => __(':session was due :minutes minutes ago. :count waiting. Mark Late or Start?', [
                'session' => $session->session_name,
                'minutes' => $minutesLate,
                'count' => $waitingCount,
            ]),
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     session_id: int,
     *     session_name: string,
     *     schedule_session_id: int,
     *     live_session_id: int|null,
     *     minutes_late: int,
     *     waiting_count: int,
     *     delay_minutes: int,
     *     suggested_delay_minutes: int|null,
     *     minutes_until_announced: int|null,
     *     minutes_past_announced: int|null,
     *     announced_at: Carbon|null,
     *     message: string,
     * }|null
     */
    private function idleAfterStartPrompt(
        ScheduleSession $session,
        LiveSession $live,
        Carbon $now,
    ): ?array {
        if (! $live->started_at) {
            return null;
        }

        $minutesSinceStart = (int) $live->started_at->diffInMinutes($now);
        if ($minutesSinceStart < self::GRACE_MINUTES) {
            return null;
        }

        $waitingCount = $this->waitingCount($session, $now);
        if ($waitingCount < 1) {
            return null;
        }

        $anyCalled = Booking::query()
            ->where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $session->id)
            ->where('booking_date', $now->toDateString())
            ->whereIn('status', ['called', 'in_chamber', 'completed'])
            ->exists();

        if ($anyCalled || $live->current_booking_id) {
            return null;
        }

        return [
            'kind' => 'idle_after_start',
            'session_id' => $session->id,
            'session_name' => $session->session_name,
            'schedule_session_id' => $session->id,
            'live_session_id' => $live->id,
            'minutes_late' => $minutesSinceStart,
            'waiting_count' => $waitingCount,
            'delay_minutes' => 0,
            'suggested_delay_minutes' => null,
            'minutes_until_announced' => null,
            'minutes_past_announced' => null,
            'announced_at' => null,
            'message' => __(':session started :minutes minutes ago. :count waiting. Nobody has been called. Is the doctor in the chair?', [
                'session' => $session->session_name,
                'minutes' => $minutesSinceStart,
                'count' => $waitingCount,
            ]),
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     session_id: int,
     *     session_name: string,
     *     schedule_session_id: int,
     *     live_session_id: int|null,
     *     minutes_late: int,
     *     waiting_count: int,
     *     delay_minutes: int,
     *     suggested_delay_minutes: int|null,
     *     minutes_until_announced: int|null,
     *     minutes_past_announced: int|null,
     *     announced_at: Carbon|null,
     *     message: string,
     * }|null
     */
    private function delayedPrompt(
        ScheduleSession $session,
        LiveSession $live,
        Carbon $sittingStart,
        int $waitingCount,
        Carbon $now,
    ): ?array {
        $delayMinutes = (int) $live->delay_minutes;
        $announcedAt = $sittingStart->copy()->addMinutes($delayMinutes);
        $minutesLate = (int) $sittingStart->diffInMinutes($now);

        if ($now->lt($announcedAt)) {
            $minutesUntil = (int) $now->diffInMinutes($announcedAt);

            return [
                'kind' => 'in_delay',
                'session_id' => $session->id,
                'session_name' => $session->session_name,
                'schedule_session_id' => $session->id,
                'live_session_id' => $live->id,
                'minutes_late' => $minutesLate,
                'waiting_count' => $waitingCount,
                'delay_minutes' => $delayMinutes,
                'suggested_delay_minutes' => null,
                'minutes_until_announced' => $minutesUntil,
                'minutes_past_announced' => null,
                'announced_at' => $announcedAt,
                'message' => __('You told them :delay. :minutes minutes left. Start now or wait?', [
                    'delay' => $this->formatDelayMinutes($delayMinutes),
                    'minutes' => $minutesUntil,
                ]),
            ];
        }

        $minutesPast = (int) $announcedAt->diffInMinutes($now);

        return [
            'kind' => 'delay_expired',
            'session_id' => $session->id,
            'session_name' => $session->session_name,
            'schedule_session_id' => $session->id,
            'live_session_id' => $live->id,
            'minutes_late' => $minutesLate,
            'waiting_count' => $waitingCount,
            'delay_minutes' => $delayMinutes,
            'suggested_delay_minutes' => $this->suggestedDelayMinutes($minutesLate, $delayMinutes),
            'minutes_until_announced' => null,
            'minutes_past_announced' => $minutesPast,
            'announced_at' => $announcedAt,
            'message' => __('You told them :delay. It has been :minutes. Add more time, or cancel.', [
                'delay' => $this->formatDelayMinutes($delayMinutes),
                'minutes' => $minutesPast,
            ]),
        ];
    }

    public function sittingTimeHasPassed(ScheduleSession $session, ?LiveSession $live = null, ?Carbon $now = null): bool
    {
        $now ??= now();

        if ($live && in_array($live->status, ['active', 'paused', 'completed', 'cancelled'], true)) {
            return false;
        }

        return $now->gte($this->sittingStart($session, $now));
    }

    /**
     * What kind of confirmation the Start button needs, if any.
     *
     * @return null|'late_without_notice'|'early_during_delay'
     */
    public function startModalKind(ScheduleSession $session, ?LiveSession $live, ?Carbon $now = null): ?string
    {
        $now ??= now();

        if ($live && in_array($live->status, ['active', 'paused', 'completed', 'cancelled'], true)) {
            return null;
        }

        if ($live && $live->status === 'delayed') {
            $announcedAt = $this->sittingStart($session, $now)->addMinutes((int) $live->delay_minutes);
            if ($now->lt($announcedAt)) {
                return 'early_during_delay';
            }

            return 'late_without_notice';
        }

        if ($this->sittingTimeHasPassed($session, $live, $now)) {
            return 'late_without_notice';
        }

        return null;
    }

    public function suggestedDelayMinutes(int $minutesLate, int $currentDelay = 0): int
    {
        foreach (self::DELAY_OPTIONS as $option) {
            if ($option >= $minutesLate && $option > $currentDelay) {
                return $option;
            }
        }

        return self::DELAY_OPTIONS[array_key_last(self::DELAY_OPTIONS)];
    }

    /**
     * @return array<int, string>
     */
    public function delayOptionsFor(int $currentDelay = 0): array
    {
        $labels = [
            15 => '15 minutes',
            30 => '30 minutes',
            45 => '45 minutes',
            60 => '1 hour',
            90 => '1.5 hours',
            120 => '2 hours',
        ];

        if ($currentDelay < 1) {
            return $labels;
        }

        return array_filter(
            $labels,
            fn (string $label, int $minutes) => $minutes > $currentDelay,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function sittingStart(ScheduleSession $session, Carbon $now): Carbon
    {
        return Carbon::parse($session->start_time)->setDateFrom($now);
    }

    private function waitingCount(ScheduleSession $session, Carbon $now): int
    {
        return Booking::query()
            ->where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $session->id)
            ->where('booking_date', $now->toDateString())
            ->whereIn('status', ['waiting', 'called', 'skipped'])
            ->count();
    }

    private function formatDelayMinutes(int $minutes): string
    {
        return match ($minutes) {
            60 => __('1 hour'),
            90 => __('1.5 hours'),
            120 => __('2 hours'),
            default => (string) $minutes,
        };
    }
}
