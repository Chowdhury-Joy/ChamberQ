<?php

namespace App\Jobs;

use App\Models\StaffPushSubscription;
use App\Models\User;
use App\Services\WebPush\DeliverWebPush;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pocket buzz for staff when a sitting sticky note appears or changes kind.
 */
class SendStaffSittingPromptPushes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $buzzKey,
        /** @var list<array{message: string, session_name: string}> */
        public readonly array $promptSummaries,
    ) {}

    public function handle(): void
    {
        if (! tenancy()->initialized || tenant('id') !== $this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);

            if (! $tenant) {
                return;
            }

            tenancy()->initialize($tenant);
        }

        if ($this->buzzKey === '' || $this->promptSummaries === []) {
            return;
        }

        $subs = StaffPushSubscription::query()->with('user')->get();

        if ($subs->isEmpty()) {
            return;
        }

        $headline = $this->promptSummaries[0]['message'];
        $title = 'Sitting needs you';
        $url = \App\Filament\TenantAdmin\Pages\LiveQueueControl::getUrl();

        foreach ($subs as $subscription) {
            if ($subscription->last_buzz_key === $this->buzzKey) {
                continue;
            }

            try {
                $result = DeliverWebPush::send(
                    $subscription->endpoint,
                    $subscription->p256dh,
                    $subscription->auth_token,
                    [
                        'title' => $title,
                        'body' => $headline,
                        'url' => $url,
                    ],
                );
            } catch (Throwable $e) {
                Log::warning('webpush.staff_buzz_failed', [
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($result === 'gone') {
                $subscription->delete();

                continue;
            }

            if ($result === 'ok') {
                $subscription->update(['last_buzz_key' => $this->buzzKey]);
            }

            $user = $subscription->user;
            if ($user instanceof User) {
                Notification::make()
                    ->title($title)
                    ->body($headline)
                    ->warning()
                    ->sendToDatabase($user);
            }
        }
    }
}
