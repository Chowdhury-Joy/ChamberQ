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
 * Text every waiting patient that the doctor is running late.
 *
 * This used to be a loop inside `LiveSessionService::markDelay()`, running in
 * the staff member's own request. Each send waits up to ten seconds for the
 * gateway (`config('sms.http.timeout')`), so a busy evening with thirty people
 * waiting could hold the Live Queue Control screen for minutes with no
 * feedback — the one moment staff most need the page to respond.
 *
 * Dispatched with `->afterResponse()`, deliberately, **not** onto the queue:
 * this application runs no queue worker, so a queued job would simply never be
 * delivered and nobody would notice patients had gone untold. After-response
 * runs in the same process once the response has been sent, which needs no
 * infrastructure. If a worker is ever added, drop `->afterResponse()` at the
 * call site and this becomes a real background job unchanged — `ShouldQueue`
 * and the tenant id below are here so that upgrade is a one-word change
 * (`QueueTenancyBootstrapper` is already enabled in `config/tenancy.php`).
 */
class SendDoctorLateNotices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>  $bookingIds  Ids, not models: the queue serialises
     *        these, and a booking's status may have moved on by the time this
     *        runs — it is re-read below.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $bookingIds,
        public readonly int $delayMinutes,
    ) {}

    public function handle(SmsService $sms): void
    {
        if ($this->bookingIds === []) {
            return;
        }

        // Safe whether or not tenancy is already initialised: after-response
        // runs in the original request (it is), a queue worker would not be.
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
            try {
                $sms->sendDoctorLateNotices($booking, $this->delayMinutes);
            } catch (Throwable $e) {
                // One unreachable number must not stop the rest of the queue
                // from being told. SmsService already records and refunds its
                // own failures; this is the belt for anything it re-throws.
                Log::warning('sms.doctor_late_notice_failed', [
                    'booking_id' => $booking->id,
                    'tenant_id' => $this->tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
