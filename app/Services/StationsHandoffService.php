<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use Carbon\Carbon;
use InvalidArgumentException;

class StationsHandoffService
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly VoucherService $voucherService,
    ) {}

    public function sendVisitToIntervention(Booking $visitBooking): Booking
    {
        if (! tenant()?->hasStations()) {
            throw new InvalidArgumentException(__('Stations module is not enabled.'));
        }

        if ($visitBooking->bookable_type !== ScheduleSession::class) {
            throw new InvalidArgumentException(__('Only sitting bookings can be sent to intervention.'));
        }

        $visitSession = $visitBooking->bookable;
        if (! $visitSession instanceof ScheduleSession || $visitSession->kind !== ScheduleSession::KIND_VISIT) {
            throw new InvalidArgumentException(__('Send to intervention is only available from a visit sitting.'));
        }

        $interventionSession = ScheduleSession::query()
            ->where('chamber_id', $visitSession->chamber_id)
            ->where('doctor_id', $visitSession->doctor_id)
            ->where('day_of_week', Carbon::parse($visitBooking->booking_date)->dayOfWeek)
            ->where('kind', ScheduleSession::KIND_INTERVENTION)
            ->first();

        if (! $interventionSession) {
            throw new InvalidArgumentException(__('No intervention sitting is scheduled for this doctor today.'));
        }

        $procedureBooking = $this->bookingService->createBookingForBookable(
            $interventionSession,
            $visitBooking->booking_date->toDateString(),
            $visitBooking->patient_name,
            $visitBooking->patient_phone,
            [],
            sendSms: false,
            patientId: $visitBooking->patient_id,
            whatsappPhone: $visitBooking->whatsapp_phone,
            allowOverflow: true,
        );

        $procedureBooking->update([
            'related_booking_id' => $visitBooking->id,
            'procedure_status' => Booking::PROCEDURE_LOGGED,
        ]);

        $this->voucherService->assignIfNeeded($procedureBooking);

        if ($visitBooking->voucher_number === null) {
            $this->voucherService->assignIfNeeded($visitBooking);
        }

        return $procedureBooking->fresh();
    }

    public function advanceProcedureStatus(Booking $booking, string $status): void
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            throw new InvalidArgumentException(__('Procedure status applies to sitting bookings only.'));
        }

        $session = $booking->bookable;
        if (! $session instanceof ScheduleSession || $session->kind !== ScheduleSession::KIND_INTERVENTION) {
            throw new InvalidArgumentException(__('Procedure status applies to intervention rows only.'));
        }

        $allowed = [
            Booking::PROCEDURE_LOGGED,
            Booking::PROCEDURE_PREPPED,
            Booking::PROCEDURE_DOCTOR_CALLED,
            Booking::PROCEDURE_DONE,
        ];

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException(__('Unknown procedure status.'));
        }

        $booking->update(['procedure_status' => $status]);
    }
}
