<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Optional desk extra: put one patient on later sittings of the same
 * schedule. Each date is a normal booking (published cap, not overflow).
 * Admin must enable it per doctor; the live queue is unchanged.
 */
class RepeatBookingService
{
    public const MAX_WEEKS = 12;

    public const SEARCH_WEEKS = 16;

    public function __construct(private BookingService $bookings) {}

    /**
     * @return array{series_id: string, created: list<Booking>, skipped: list<array{date: string, reason: string}>}
     */
    public function repeatFromBooking(Booking $origin, int $weeks): array
    {
        $this->assertCanRepeat($origin, $weeks);

        $plan = $this->planDates($origin, $weeks);
        $seriesId = $origin->repeat_series_id ?: (string) Str::uuid();

        if ($origin->repeat_series_id !== $seriesId) {
            $origin->forceFill(['repeat_series_id' => $seriesId])->save();
        }

        $created = [];

        foreach ($plan['dates'] as $date) {
            try {
                $created[] = $this->bookings->createBookingForBookable(
                    $origin->bookable,
                    $date,
                    $origin->patient_name,
                    $origin->patient_phone,
                    [],
                    sendSms: false,
                    patientId: $origin->patient_id,
                    whatsappPhone: $origin->whatsapp_phone,
                    allowOverflow: false,
                    repeatSeriesId: $seriesId,
                );
            } catch (BookingUnavailableException $e) {
                $plan['skipped'][] = [
                    'date' => $date,
                    'reason' => $this->skipReasonFromException($e),
                ];
            }
        }

        return [
            'series_id' => $seriesId,
            'created' => $created,
            'skipped' => $plan['skipped'],
        ];
    }

    /**
     * @return array{dates: list<string>, skipped: list<array{date: string, reason: string}>}
     */
    public function planDates(Booking $origin, int $weeks): array
    {
        $this->assertCanRepeat($origin, $weeks);

        $session = $origin->bookable;
        $wanted = $weeks;
        $dates = [];
        $skipped = [];
        $cursor = Carbon::parse($origin->booking_date->toDateString(), OperationalReportService::TIMEZONE)
            ->addDay()
            ->startOfDay();
        $limit = $cursor->copy()->addWeeks(self::SEARCH_WEEKS);

        while (count($dates) < $wanted && $cursor->lte($limit)) {
            if ($cursor->dayOfWeek !== (int) $session->day_of_week) {
                $cursor->addDay();

                continue;
            }

            $ymd = $cursor->toDateString();
            $availability = $this->bookings->availabilityFor($session, $ymd, allowOverflow: false);

            if ($availability['blocked']) {
                $skipped[] = ['date' => $ymd, 'reason' => 'blocked'];
            } elseif ($availability['remaining'] <= 0) {
                $skipped[] = ['date' => $ymd, 'reason' => 'full'];
            } else {
                $dates[] = $ymd;
            }

            $cursor->addDay();
        }

        return ['dates' => $dates, 'skipped' => $skipped];
    }

    public function cancelRemainder(Booking $origin): int
    {
        $seriesId = $origin->repeat_series_id;
        if (blank($seriesId)) {
            return 0;
        }

        $originDate = $origin->booking_date->toDateString();
        $rows = Booking::query()
            ->where('repeat_series_id', $seriesId)
            ->where('id', '!=', $origin->id)
            ->where('booking_date', '>', $originDate)
            ->whereIn('status', ['waiting', 'called', 'skipped'])
            ->get();

        $reason = __('Remaining repeating serials cancelled.');

        foreach ($rows as $row) {
            $row->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();
        }

        return $rows->count();
    }

    public function doctorAllowsRepeat(Booking $booking): bool
    {
        $booking->loadMissing('bookable.doctor');

        if (! $booking->bookable instanceof ScheduleSession) {
            return false;
        }

        return (bool) $booking->bookable->doctor?->allows_repeat_serials;
    }

    private function assertCanRepeat(Booking $origin, int $weeks): void
    {
        $origin->loadMissing('bookable.doctor');

        if (! $origin->bookable instanceof ScheduleSession) {
            throw new InvalidArgumentException('Repeating serials are only for a doctor sitting, not a lab slot.');
        }

        if (! $origin->bookable->doctor?->allows_repeat_serials) {
            throw new InvalidArgumentException('Repeating serials are not enabled for this doctor.');
        }

        if ($weeks < 1 || $weeks > self::MAX_WEEKS) {
            throw new InvalidArgumentException('Choose between 1 and '.self::MAX_WEEKS.' future sittings.');
        }
    }

    private function skipReasonFromException(BookingUnavailableException $e): string
    {
        return match ($e->errorCode) {
            'blocked' => 'blocked',
            'capacity' => 'full',
            'duplicate' => 'duplicate',
            default => $e->errorCode,
        };
    }
}
