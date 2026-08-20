<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Text patients whose serial was cancelled because the sitting ended early
 * or the doctor was marked absent.
 *
 * Same shape as `SendDoctorLateNotices`: after-response, not the database
 * queue — this application runs no queue worker. Only fires when that
 * doctor's Cancellation SMS switch is on (checked at dispatch).
 */
class SendSessionCancellationNotices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>  $bookingIds
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $bookingIds,
    ) {}

    public function handle(SmsService $sms): void
    {
        if ($this->bookingIds === []) {
            return;
        }

        if (! tenancy()->initialized || tenant('id') !== $this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);

            if (! $tenant) {
                return;
            }

            tenancy()->initialize($tenant);
        }

        $bookings = Booking::whereIn('id', $this->bookingIds)
            ->orderBy('serial_number')
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->status !== 'cancelled') {
                continue;
            }

            try {
                $sms->sendCancellationNotice($booking);
            } catch (Throwable $e) {
                Log::warning('sms.session_cancellation_notice_failed', [
                    'booking_id' => $booking->id,
                    'tenant_id' => $this->tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
