<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-SMS the after-visit share (prescription and/or Google review) once
 * the visit is completed, when that doctor's After visit Auto SMS is on.
 *
 * Same after-response / no-worker shape as SendBookingConfirmation.
 */
class SendVisitShareNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $bookingId,
    ) {}

    public function handle(SmsService $sms): void
    {
        if (! tenancy()->initialized || tenant('id') !== $this->tenantId) {
            $tenant = Tenant::find($this->tenantId);

            if (! $tenant) {
                return;
            }

            tenancy()->initialize($tenant);
        }

        $booking = Booking::find($this->bookingId);

        if (! $booking || $booking->status !== 'completed') {
            return;
        }

        $doctor = Doctor::resolveForBooking($booking);

        if (! $doctor?->wantsAutoSms(Doctor::NOTIFY_PRESCRIPTION)) {
            return;
        }

        $booking->loadMissing('visitRecord.prescription');
        $prescription = $booking->visitRecord?->prescription;

        try {
            if ($prescription) {
                $sms->sendPrescriptionNotice($booking, $prescription, staffTap: false);
            } elseif (Chamber::reviewUrlForBooking($booking) !== null) {
                $sms->sendReviewNotice($booking, staffTap: false);
            }
        } catch (Throwable $e) {
            Log::warning('sms.visit_share_auto_failed', [
                'booking_id' => $this->bookingId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
