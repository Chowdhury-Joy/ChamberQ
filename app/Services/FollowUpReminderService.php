<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VisitRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class FollowUpReminderService
{
    public const DAYS_BEFORE = 3;

    public function __construct(
        private readonly SmsService $sms,
    ) {}

    /**
     * @return array{sms_sent: int, whatsapp_queued: int, failed: int}
     */
    public function processTenant(): array
    {
        $targetDate = now()->addDays(self::DAYS_BEFORE)->toDateString();
        $dayAfter = now()->addDays(self::DAYS_BEFORE + 1)->toDateString();
        $smsSent = 0;
        $whatsappQueued = 0;
        $failed = 0;

        $visits = VisitRecord::query()
            ->where('follow_up_date', '>=', $targetDate)
            ->where('follow_up_date', '<', $dayAfter)
            ->with(['booking.bookable.doctor'])
            ->get();

        foreach ($visits as $visit) {
            // One patient's bad row must not cost the rest of the clinic their
            // reminder. A throw here — an SMS gateway timeout, a doctor profile
            // that will not resolve — used to abort this whole loop and, from
            // the command above, every tenant left in the cursor.
            try {
                if ($this->remindForVisit($visit, $smsSent, $whatsappQueued)) {
                    continue;
                }
            } catch (Throwable $e) {
                $failed++;

                Log::error('follow_up_reminder.failed', [
                    'tenant_id' => tenant('id'),
                    'visit_record_id' => $visit->id,
                    'booking_id' => $visit->booking_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($whatsappQueued > 0) {
            // Also isolated: the reminders themselves are already sent by this
            // point, so a failure to notify staff must not discard the counts.
            try {
                $this->notifyWhatsappHandlers($whatsappQueued);
            } catch (Throwable $e) {
                Log::error('follow_up_reminder.staff_notify_failed', [
                    'tenant_id' => tenant('id'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sms_sent' => $smsSent, 'whatsapp_queued' => $whatsappQueued, 'failed' => $failed];
    }

    /**
     * Send / queue this one visit's reminder.
     *
     * @return bool True when there was nothing to do for this visit.
     */
    private function remindForVisit(VisitRecord $visit, int &$smsSent, int &$whatsappQueued): bool
    {
        $booking = $visit->booking;

        if (! $booking instanceof Booking || blank($booking->patient_phone)) {
            return true;
        }

        $doctor = Doctor::resolveForBooking($booking);

        if (! $doctor) {
            return true;
        }

        if ($doctor->wantsSms(Doctor::NOTIFY_FOLLOW_UP) && $visit->follow_up_reminder_sms_sent_at === null) {
            $message = $this->sms->sendFollowUpReminder($booking, $visit, $doctor);

            if ($message?->status === SmsMessage::STATUS_SENT) {
                $visit->forceFill(['follow_up_reminder_sms_sent_at' => now()])->save();
                $smsSent++;
            }
        }

        if ($doctor->wantsWhatsapp(Doctor::NOTIFY_FOLLOW_UP)
            && $visit->follow_up_reminder_whatsapp_queued_at === null
            && $visit->follow_up_reminder_whatsapp_sent_at === null) {
            $visit->forceFill(['follow_up_reminder_whatsapp_queued_at' => now()])->save();
            $whatsappQueued++;
        }

        return false;
    }

    public function whatsappMessage(Booking $booking, VisitRecord $visit, Doctor $doctor): string
    {
        return $this->sms->followUpReminderBody($booking, $visit, $doctor);
    }

    private function notifyWhatsappHandlers(int $count): void
    {
        $recipients = User::query()
            ->where('role', User::ROLE_STAFF)
            ->get();

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_DOCTOR])
                ->get();
        }

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('Follow-up WhatsApp reminders ready'))
            ->body(trans_choice(
                '{1} patient needs a follow-up WhatsApp reminder. Open Operations → Follow-up reminders.|[2,*] :count patients need follow-up WhatsApp reminders. Open Operations → Follow-up reminders.',
                $count,
                ['count' => $count],
            ))
            ->warning()
            ->sendToDatabase($recipients);
    }
}
