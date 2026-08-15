<?php

namespace App\Jobs;

use App\Contracts\WebPushSender;
use App\Models\Booking;
use App\Models\BookingPushSubscription;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use App\Support\TenancyUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pocket buzz when a subscribed ticket is two away, next, or called.
 *
 * No SMS and no WhatsApp — this is web-push only. Dispatched with
 * `->afterResponse()` for the same reason as SendDoctorLateNotices:
 * this app runs no queue worker.
 */
class SendQueueApproachPushes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $liveSessionId,
    ) {}

    public function handle(WebPushSender $sender, LiveSessionService $liveSessions): void
    {
        if (! tenancy()->initialized || tenant('id') !== $this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);

            if (! $tenant) {
                return;
            }

            tenancy()->initialize($tenant);
        }

        if (! tenant()?->hasLiveQueue()) {
            return;
        }

        $liveSession = LiveSession::query()->find($this->liveSessionId);

        if (! $liveSession) {
            return;
        }

        $bookings = $liveSession->bookings()
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('serial_number')
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $subs = BookingPushSubscription::query()
            ->whereIn('booking_id', $bookings->modelKeys())
            ->get()
            ->groupBy('booking_id');

        if ($subs->isEmpty()) {
            return;
        }

        $previous = App::getLocale();
        App::setLocale('bn');

        try {
            foreach ($bookings as $booking) {
                $stage = $this->stageFor($booking, $liveSession, $liveSessions);

                if ($stage === null) {
                    continue;
                }

                foreach ($subs->get($booking->id, collect()) as $subscription) {
                    if ($subscription->alreadySent($stage)) {
                        continue;
                    }

                    try {
                        $result = $sender->send($subscription, $this->payload($booking, $stage));
                    } catch (Throwable $e) {
                        Log::warning('webpush.approach_failed', [
                            'booking_id' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }

                    if ($result === 'gone') {
                        $subscription->delete();

                        continue;
                    }

                    if ($result === 'ok') {
                        $subscription->update(['last_stage' => $stage]);
                    }
                }
            }
        } finally {
            App::setLocale($previous);
        }
    }

    private function stageFor(Booking $booking, LiveSession $liveSession, LiveSessionService $liveSessions): ?string
    {
        if ($booking->status === 'called') {
            return BookingPushSubscription::STAGE_CALLED;
        }

        $ahead = $liveSessions->peopleAheadOf($booking, $liveSession);

        if ($ahead <= 1) {
            return BookingPushSubscription::STAGE_NEXT;
        }

        if ($ahead === 2) {
            return BookingPushSubscription::STAGE_TWO_AWAY;
        }

        return null;
    }

    /**
     * @return array{title: string, body: string, url: string, stage: string}
     */
    private function payload(Booking $booking, string $stage): array
    {
        [$title, $body] = match ($stage) {
            BookingPushSubscription::STAGE_CALLED => [
                __('Your turn', [], 'bn'),
                __('It is your turn! Please enter the chamber.', [], 'bn'),
            ],
            BookingPushSubscription::STAGE_NEXT => [
                __('You are next.', [], 'bn'),
                __('You are next — stay close.', [], 'bn'),
            ],
            default => [
                __('Come back to the chamber', [], 'bn'),
                __('Start walking back — you are soon.', [], 'bn'),
            ],
        };

        return [
            'title' => $title,
            'body' => $body,
            'url' => TenancyUrl::publicAbsolute((string) $booking->tenant_id, '/bookings/'.$booking->id),
            'stage' => $stage,
        ];
    }
}
