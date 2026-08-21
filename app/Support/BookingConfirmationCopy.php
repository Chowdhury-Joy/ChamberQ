<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Services\PublishedComeAround;
use Carbon\Carbon;

/**
 * Shared desk confirmation: Book serial modal + Push WhatsApp paste.
 *
 * Come-around matches booking SMS / the public wizard (published guess
 * until Start). WhatsApp is not GSM-capped, so it can also carry hours,
 * centre, and the ticket link.
 */
final class BookingConfirmationCopy
{
    /**
     * @return array{
     *     serial: int,
     *     name: string,
     *     phone: string,
     *     date: string,
     *     chamber: ?string,
     *     doctor: ?string,
     *     sitting: string,
     *     room: ?string,
     *     hours: ?string,
     *     procedure: ?string,
     *     come_around: ?string,
     *     overflow_phrase: ?string,
     *     ticket: string,
     *     whatsapp: ?string,
     *     sms_url: ?string,
     *     auto_sms: bool
     * }
     */
    public static function modalState(Booking $booking): array
    {
        $facts = self::facts($booking);
        $doctor = Doctor::resolveForBooking($booking);
        $ticketUrl = $booking->publicTicketUrl();

        return [
            'serial' => (int) $booking->serial_number,
            'name' => (string) $booking->patient_name,
            'phone' => (string) $booking->patient_phone,
            'date' => $facts['date'],
            'chamber' => $facts['chamber'],
            'doctor' => $facts['doctor'],
            'sitting' => $facts['sitting'],
            'room' => $facts['room'],
            'hours' => $facts['hours'],
            'procedure' => $facts['procedure'],
            'come_around' => $facts['come_around'],
            'overflow_phrase' => $facts['overflow_phrase'],
            'ticket' => $ticketUrl,
            'whatsapp' => ($doctor?->wantsWhatsapp(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false)
                ? $booking->whatsappLink(self::whatsappMessage($booking, $facts, $ticketUrl))
                : null,
            'sms_url' => ($doctor?->wantsPushSms(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false)
                ? tenant_web_route('bookings.sms.confirmation', $booking)
                : null,
            'auto_sms' => $doctor?->wantsAutoSms(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false,
        ];
    }

    public static function whatsappMessage(Booking $booking, ?array $facts = null, ?string $ticketUrl = null): string
    {
        $facts ??= self::facts($booking);
        $ticketUrl ??= $booking->publicTicketUrl();

        $lines = [
            __('Hello :name, your serial is :serial on :date.', [
                'name' => $booking->patient_name,
                'serial' => $booking->serial_number,
                'date' => $facts['date'],
            ]),
        ];

        if (filled($facts['come_around'])) {
            $lines[] = __('Come around :time', ['time' => $facts['come_around']]);
        } elseif (filled($facts['overflow_phrase'])) {
            $lines[] = $facts['overflow_phrase'];
        }

        $detail = implode(' · ', array_filter([
            $facts['sitting'],
            $facts['hours'],
            $facts['procedure'],
            $facts['doctor'],
        ], fn ($part): bool => filled($part)));

        if ($detail !== '') {
            $lines[] = $detail;
        }

        if (filled($facts['chamber'])) {
            $lines[] = $facts['chamber'];
        }

        $lines[] = __('Ticket').': '.$ticketUrl;

        return implode("\n", $lines);
    }

    /**
     * @return array{
     *     date: string,
     *     chamber: ?string,
     *     doctor: ?string,
     *     sitting: string,
     *     room: ?string,
     *     hours: ?string,
     *     procedure: ?string,
     *     come_around: ?string,
     *     overflow_phrase: ?string
     * }
     */
    public static function facts(Booking $booking): array
    {
        $booking->loadMissing(['bookable.chamber', 'bookable.doctor', 'feeCatalogItem']);

        $date = $booking->booking_date?->translatedFormat('j F Y') ?? '';
        $chamber = null;
        $doctor = null;
        $sitting = __('Sitting');
        $room = null;
        $hours = null;
        $procedure = $booking->feeCatalogItem?->label;
        $comeAround = null;
        $overflowPhrase = null;

        $bookable = $booking->bookable;

        if ($bookable instanceof LabCollectionSlot) {
            $sitting = __('Lab collection');
            $chamber = $bookable->chamber?->name;
            $hours = self::hoursLabel($bookable->start_time, $bookable->end_time);
        } elseif ($bookable instanceof ScheduleSession) {
            $sitting = $bookable->session_name ?: __('Sitting');
            $chamber = $bookable->chamber?->name;
            $doctor = $bookable->doctor?->name;
            $hours = self::hoursLabel($bookable->start_time, $bookable->end_time);

            if (tenant()?->hasStations() && filled($bookable->kind)) {
                $room = $bookable->kindLabel();
            }

            if ($booking->is_overflow) {
                $overflowPhrase = __('After serial :n', [
                    'n' => $bookable->publishedSlotCap(),
                ]);
            } else {
                $estimate = app(PublishedComeAround::class)->estimateForBooking($booking);
                if ($estimate) {
                    $comeAround = app(PublishedComeAround::class)
                        ->formatTimeForSms($estimate['shown_time']);
                }
            }
        }

        if ($doctor === null) {
            $doctor = Doctor::resolveForBooking($booking)?->name;
        }

        return [
            'date' => $date,
            'chamber' => $chamber,
            'doctor' => $doctor,
            'sitting' => $sitting,
            'room' => $room,
            'hours' => $hours,
            'procedure' => filled($procedure) ? $procedure : null,
            'come_around' => $comeAround,
            'overflow_phrase' => $overflowPhrase,
        ];
    }

    private static function hoursLabel(mixed $start, mixed $end): ?string
    {
        if (blank($start) || blank($end)) {
            return null;
        }

        return Carbon::parse($start)->format('g:i A').' – '.Carbon::parse($end)->format('g:i A');
    }
}
