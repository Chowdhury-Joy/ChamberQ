<?php

namespace App\Jobs;

use App\Models\Booking;
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
 * Text a patient their serial, after the booking response has been sent.
 *
 * This used to run inside `BookingService::createBookingForBookable()`, in the
 * patient's own request: the serial was already committed, and then Fatima's
 * screen sat there while the server waited up to ten seconds
 * (`config('sms.http.timeout')`) for the aggregator to answer. On a slow
 * evening that is every patient, at the exact moment they are least sure the
 * booking worked — and a second tap on Confirm is what that uncertainty
 * produces.
 *
 * Dispatched with `->afterResponse()`, deliberately, **not** onto the queue —
 * the same call as `SendDoctorLateNotices`, for the same reason: this
 * application runs no queue worker, so a queued job would never be delivered
 * and no patient would ever be told their serial. After-response runs in the
 * same process once the response has been sent, and needs no infrastructure.
 * `ShouldQueue` and the tenant id are here so that adding a worker later is
 * deleting `->afterResponse()` at the call site (`QueueTenancyBootstrapper` is
 * already enabled in `config/tenancy.php`).
 *
 * The wallet debit moves with the send, which is correct: a credit should be
 * spent when a message is actually attempted, not when a row is written.
 */
class SendBookingConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $bookingId  An id, not a model: the queue serialises this,
     *        and the booking may have been cancelled between the response and
     *        this running — it is re-read below.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $bookingId,
    ) {}

    public function handle(SmsService $sms): void
    {
        // Safe whether or not tenancy is already initialised: after-response
        // runs in the original request (it is), a queue worker would not be.
        if (! tenancy()->initialized || tenant('id') !== $this->tenantId) {
            $tenant = Tenant::find($this->tenantId);

            if (! $tenant) {
                return;
            }

            tenancy()->initialize($tenant);
        }

        $booking = Booking::find($this->bookingId);

        if (! $booking) {
            return;
        }

        // Staff can cancel a serial between the wizard's response and this
        // running. Confirming a booking that no longer exists would send the
        // patient to the chamber for nothing.
        if ($booking->status === 'cancelled') {
            return;
        }

        try {
            $sms->sendBookingConfirmation($booking);
        } catch (Throwable $e) {
            // The booking is already committed and the patient already has
            // their ticket page. `SmsService` records and refunds its own
            // failures; this is the belt for anything it re-throws, so a
            // gateway outage cannot surface as a 500 on a booking that worked.
            Log::warning('sms.booking_confirmation_failed', [
                'booking_id' => $this->bookingId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
