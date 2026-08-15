<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Support\ScheduleSessionPace;
use Carbon\Carbon;

/**
 * Published come-around guess — "if the sitting starts on time."
 *
 * Used for booking SMS, wizard confirm flash, and the ticket before a live
 * session row exists. After Mark Late / Start, LiveSessionService takes over.
 */
class PublishedComeAround
{
    /**
     * @return array{actual_estimate: Carbon, shown_time: Carbon}|null
     */
    public function estimateForBooking(Booking $booking): ?array
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            return null;
        }

        if (! tenant()?->hasLiveQueue()) {
            return null;
        }

        if ($booking->is_overflow) {
            return null;
        }

        $session = $booking->bookable;
        if (! $session instanceof ScheduleSession) {
            $booking->loadMissing('bookable');
            $session = $booking->bookable;
        }

        if (! $session instanceof ScheduleSession) {
            return null;
        }

        return $this->estimateForSession(
            $session,
            (int) $booking->serial_number,
            Carbon::parse($booking->booking_date->toDateString()),
        );
    }

    /**
     * @return array{actual_estimate: Carbon, shown_time: Carbon}|null
     */
    public function estimateForSession(ScheduleSession $session, int $serial, Carbon $bookingDate): ?array
    {
        if (! tenant()?->hasLiveQueue()) {
            return null;
        }

        $avgMins = ScheduleSessionPace::minutesPerPatient($session);
        if ($avgMins === null) {
            return null;
        }

        $sittingStart = Carbon::parse($session->start_time)->setDateFrom($bookingDate);
        $actualEstimate = $sittingStart->copy()->addMinutes(max(0, $serial - 1) * $avgMins);

        $tenant = tenant();
        $firstN = $tenant?->first_n_patients ?? 2;
        $offsetMins = $tenant?->first_n_arrival_offset_minutes ?? 15;
        $bufferMins = $tenant?->estimated_time_buffer_minutes ?? 30;

        $shownTime = $serial <= $firstN
            ? $actualEstimate->copy()->subMinutes($offsetMins)
            : $actualEstimate->copy()->subMinutes($bufferMins);

        return [
            'actual_estimate' => $actualEstimate,
            'shown_time' => $shownTime,
        ];
    }

    public function formatTimeForSms(Carbon $time): string
    {
        return strtolower($time->format('g:ia'));
    }

    public function overflowSmsPhrase(ScheduleSession $session): string
    {
        return 'After serial '.ScheduleSessionPace::publishedCap($session);
    }
}
