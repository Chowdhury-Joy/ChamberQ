<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Clinic- and doctor-level rules for “is this still a follow-up?” and
 * whether report / counseling seats are free or paid.
 *
 * Stored as JSON on the tenant (default) and optionally on the doctor
 * (override). Missing keys use defaults that match today’s MUPS paper:
 * follow-up for 3 months, report and counseling free.
 */
class PracticeRules
{
    public const FOLLOW_UP_MONTHS = 'months';

    public const FOLLOW_UP_UNLIMITED = 'unlimited';

    public const FOLLOW_UP_NEVER = 'never';

    public const PRICING_ALWAYS_FREE = 'always_free';

    public const PRICING_ALWAYS_PAID = 'always_paid';

    public const PRICING_TIMED = 'timed';

    /**
     * @return array{
     *     follow_up_window: string,
     *     follow_up_months: int,
     *     report_pricing: string,
     *     report_free_for_months: int,
     *     report_price_inside_taka: int,
     *     report_price_after_taka: int,
     *     counseling_pricing: string,
     *     counseling_free_for_months: int,
     *     counseling_price_inside_taka: int,
     *     counseling_price_after_taka: int
     * }
     */
    public static function defaults(): array
    {
        return [
            'follow_up_window' => self::FOLLOW_UP_MONTHS,
            'follow_up_months' => 3,
            'report_pricing' => self::PRICING_ALWAYS_FREE,
            'report_free_for_months' => 3,
            'report_price_inside_taka' => 0,
            'report_price_after_taka' => 0,
            'counseling_pricing' => self::PRICING_ALWAYS_FREE,
            'counseling_free_for_months' => 3,
            'counseling_price_inside_taka' => 0,
            'counseling_price_after_taka' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function normalize(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        $window = $stored['follow_up_window'] ?? $defaults['follow_up_window'];
        if (! in_array($window, [self::FOLLOW_UP_MONTHS, self::FOLLOW_UP_UNLIMITED, self::FOLLOW_UP_NEVER], true)) {
            $window = $defaults['follow_up_window'];
        }

        $months = max(1, (int) ($stored['follow_up_months'] ?? $defaults['follow_up_months']));

        return [
            'follow_up_window' => $window,
            'follow_up_months' => $months,
            'report_pricing' => self::normalizePricing($stored['report_pricing'] ?? null),
            'report_free_for_months' => max(1, (int) ($stored['report_free_for_months'] ?? $defaults['report_free_for_months'])),
            'report_price_inside_taka' => max(0, (int) ($stored['report_price_inside_taka'] ?? 0)),
            'report_price_after_taka' => max(0, (int) ($stored['report_price_after_taka'] ?? 0)),
            'counseling_pricing' => self::normalizePricing($stored['counseling_pricing'] ?? null),
            'counseling_free_for_months' => max(1, (int) ($stored['counseling_free_for_months'] ?? $defaults['counseling_free_for_months'])),
            'counseling_price_inside_taka' => max(0, (int) ($stored['counseling_price_inside_taka'] ?? 0)),
            'counseling_price_after_taka' => max(0, (int) ($stored['counseling_price_after_taka'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forDoctor(?Doctor $doctor, ?Tenant $tenant = null): array
    {
        $clinic = self::normalize($tenant?->practice_rules ?? tenant()?->practice_rules);

        if ($doctor === null) {
            return $clinic;
        }

        $override = $doctor->practice_rules;

        if (! is_array($override) || $override === []) {
            return $clinic;
        }

        return self::normalize(array_merge($clinic, $override));
    }

    public static function forBooking(Booking $booking): array
    {
        $booking->loadMissing('bookable.doctor');

        return self::forDoctor(Doctor::resolveForBooking($booking), tenant());
    }

    public static function isFollowUpEligible(?Patient $patient, ?Doctor $doctor = null, ?Tenant $tenant = null): bool
    {
        if ($patient === null) {
            return false;
        }

        $rules = self::forDoctor($doctor, $tenant);

        return match ($rules['follow_up_window']) {
            self::FOLLOW_UP_NEVER => false,
            self::FOLLOW_UP_UNLIMITED => self::hasPreviousCompletedVisit($patient)
                || (bool) $patient->seen_before_software,
            default => self::hasPreviousCompletedVisit($patient, $rules['follow_up_months']),
        };
    }

    public static function bookingIsFeeExempt(Booking $booking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        $booking->loadMissing('bookable');
        $session = $booking->bookable;

        if (! $session instanceof ScheduleSession) {
            return false;
        }

        if ($session->kind === ScheduleSession::KIND_CONSULT) {
            return true;
        }

        $kind = $session->kind;
        if (! in_array($kind, [ScheduleSession::KIND_REPORT, ScheduleSession::KIND_COUNSELING], true)) {
            return false;
        }

        $rules = self::forBooking($booking);
        $prefix = $kind === ScheduleSession::KIND_REPORT ? 'report' : 'counseling';
        $pricing = $rules[$prefix.'_pricing'];

        if ($pricing === self::PRICING_ALWAYS_FREE) {
            return true;
        }

        if ($pricing === self::PRICING_ALWAYS_PAID) {
            return ((int) $rules[$prefix.'_price_after_taka']) < 1
                && ((int) $rules[$prefix.'_price_inside_taka']) < 1;
        }

        $inside = self::isInsideTimedWindow($booking, (int) $rules[$prefix.'_free_for_months']);
        $amount = $inside
            ? (int) $rules[$prefix.'_price_inside_taka']
            : (int) $rules[$prefix.'_price_after_taka'];

        return $amount < 1;
    }

    public static function suggestedRoomFeeTaka(Booking $booking): int
    {
        $booking->loadMissing('bookable');
        $session = $booking->bookable;

        if (! $session instanceof ScheduleSession) {
            return 0;
        }

        $kind = $session->kind;
        if (! in_array($kind, [ScheduleSession::KIND_REPORT, ScheduleSession::KIND_COUNSELING], true)) {
            return 0;
        }

        $rules = self::forBooking($booking);
        $prefix = $kind === ScheduleSession::KIND_REPORT ? 'report' : 'counseling';
        $pricing = $rules[$prefix.'_pricing'];

        if ($pricing === self::PRICING_ALWAYS_FREE) {
            return 0;
        }

        if ($pricing === self::PRICING_ALWAYS_PAID) {
            return max(
                (int) $rules[$prefix.'_price_after_taka'],
                (int) $rules[$prefix.'_price_inside_taka'],
            );
        }

        $inside = self::isInsideTimedWindow($booking, (int) $rules[$prefix.'_free_for_months']);

        return $inside
            ? (int) $rules[$prefix.'_price_inside_taka']
            : (int) $rules[$prefix.'_price_after_taka'];
    }

    private static function hasPreviousCompletedVisit(Patient $patient, ?int $withinMonths = null): bool
    {
        $query = Booking::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->whereDate('booking_date', '<', Carbon::today()->toDateString());

        if ($withinMonths !== null) {
            $query->whereDate(
                'booking_date',
                '>=',
                Carbon::today()->subMonthsNoOverflow($withinMonths)->toDateString(),
            );
        }

        return $query->exists();
    }

    private static function isInsideTimedWindow(Booking $booking, int $months): bool
    {
        $anchor = self::feeAnchorDate($booking);

        if ($anchor === null) {
            return false;
        }

        $start = Carbon::today()->subMonthsNoOverflow($months)->toDateString();

        return $anchor->toDateString() >= $start;
    }

    private static function feeAnchorDate(Booking $booking): ?Carbon
    {
        $booking->loadMissing('careOrigin');
        $origin = $booking->careOrigin;

        if ($origin?->booking_date) {
            return Carbon::parse($origin->booking_date->toDateString());
        }

        $patientId = $booking->patient_id;
        if (! filled($patientId)) {
            return $booking->booking_date
                ? Carbon::parse($booking->booking_date->toDateString())
                : null;
        }

        $last = Booking::query()
            ->where('patient_id', $patientId)
            ->where('status', 'completed')
            ->where('id', '!=', $booking->id)
            ->orderByDesc('booking_date')
            ->first();

        if ($last?->booking_date) {
            return Carbon::parse($last->booking_date->toDateString());
        }

        return $booking->booking_date
            ? Carbon::parse($booking->booking_date->toDateString())
            : null;
    }

    private static function normalizePricing(mixed $value): string
    {
        if (in_array($value, [self::PRICING_ALWAYS_FREE, self::PRICING_ALWAYS_PAID, self::PRICING_TIMED], true)) {
            return $value;
        }

        return self::PRICING_ALWAYS_FREE;
    }
}
