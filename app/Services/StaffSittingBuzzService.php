<?php

namespace App\Services;

use App\Jobs\SendStaffSittingPromptPushes;
use App\Models\StaffPushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StaffSittingBuzzService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $prompts
     */
    public function dispatchForPrompts(Collection $prompts): void
    {
        if (! tenant()?->hasLiveQueue()) {
            return;
        }

        $buzzKey = $this->buzzKey($prompts);

        if ($buzzKey === '') {
            StaffPushSubscription::query()->update(['last_buzz_key' => '']);

            return;
        }

        $tenantId = (string) tenant('id');
        $cacheKey = 'staff_sitting_buzz:'.$tenantId;

        if (Cache::get($cacheKey) === $buzzKey) {
            return;
        }

        Cache::put($cacheKey, $buzzKey, now()->addHours(12));

        $summaries = $prompts
            ->map(fn (array $prompt) => [
                'message' => (string) ($prompt['message'] ?? ''),
                'session_name' => (string) ($prompt['session_name'] ?? ''),
            ])
            ->values()
            ->all();

        SendStaffSittingPromptPushes::dispatch(
            (string) tenant('id'),
            $buzzKey,
            $summaries,
        )->afterResponse();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $prompts
     */
    private function buzzKey(Collection $prompts): string
    {
        if ($prompts->isEmpty()) {
            return '';
        }

        $parts = $prompts
            ->sortBy('schedule_session_id')
            ->map(fn (array $prompt) => [
                (int) ($prompt['schedule_session_id'] ?? 0),
                (string) ($prompt['kind'] ?? ''),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }
}
