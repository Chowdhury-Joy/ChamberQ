<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStationsMorningCountPushes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{visit_waiting: int, intervention_waiting: int, date: string}  $summary
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $summary,
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

        $body = __(':visits visits and :procedures procedures waiting today.', [
            'visits' => $this->summary['visit_waiting'],
            'procedures' => $this->summary['intervention_waiting'],
        ]);

        User::query()
            ->whereIn('role', [User::ROLE_DOCTOR, User::ROLE_ADMIN, User::ROLE_HELPER])
            ->each(function (User $user) use ($body): void {
                Notification::make()
                    ->title(__('Morning queue count'))
                    ->body($body)
                    ->info()
                    ->sendToDatabase($user);
            });
    }
}
