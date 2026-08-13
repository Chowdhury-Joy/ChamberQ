<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FollowUpReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFollowUpRemindersCommand extends Command
{
    protected $signature = 'follow-ups:send-reminders';

    protected $description = 'Send follow-up SMS reminders 3 days before the appointment and queue WhatsApp confirms';

    /**
     * Runs unattended at 07:00 daily, so every failure here is silent by
     * default. One chamber's bad row must not cost every chamber after it in
     * the cursor their reminders — these are patients being told to come back
     * for a recheck, and a missed one is not noticed by anybody until the
     * appointment simply does not happen.
     */
    public function handle(FollowUpReminderService $reminders): int
    {
        $totalSms = 0;
        $totalWhatsapp = 0;
        $totalFailedVisits = 0;
        $failedTenants = [];

        foreach (Tenant::cursor() as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $result = $reminders->processTenant();
                $totalSms += $result['sms_sent'];
                $totalWhatsapp += $result['whatsapp_queued'];
                $totalFailedVisits += $result['failed'];
            } catch (Throwable $e) {
                $failedTenants[] = (string) $tenant->getTenantKey();

                Log::error('follow_up_reminders.tenant_failed', [
                    'tenant_id' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]);

                $this->error("Follow-up reminders failed for {$tenant->getTenantKey()}: {$e->getMessage()}");
            } finally {
                // In a finally so a throw above cannot leave this tenant's
                // context bound while the loop moves on to the next one.
                tenancy()->end();
            }
        }

        $this->info("Follow-up reminders: {$totalSms} SMS sent, {$totalWhatsapp} WhatsApp queued.");

        if ($totalFailedVisits > 0) {
            $this->warn("{$totalFailedVisits} patient(s) could not be reminded — see the log.");
        }

        if ($failedTenants !== []) {
            $this->error(count($failedTenants).' chamber(s) failed entirely: '.implode(', ', $failedTenants));
        }

        // Non-zero so a scheduled run that partly failed is visible rather than
        // reported as a clean night.
        return ($failedTenants === [] && $totalFailedVisits === 0)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
