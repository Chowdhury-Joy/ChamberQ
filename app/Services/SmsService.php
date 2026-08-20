<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\VisitRecord;
use App\Support\GsmText;
use App\Support\TenancyUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmsService
{
    public function __construct(
        private readonly SmsGateway $gateway,
    ) {}

    /**
     * Debit one prepaid credit and send a booking confirmation SMS.
     *
     * Booking always stays created — empty wallet, prefs off, or gateway
     * failure only affects the SMS row (and refunds the credit on hard send failure).
     */
    public function sendBookingConfirmation(Booking $booking): ?SmsMessage
    {
        $doctor = Doctor::resolveForBooking($booking);

        if ($doctor && ! $doctor->wantsSms(Doctor::NOTIFY_BOOKING_CONFIRMATION)) {
            return $this->record(
                $booking,
                SmsMessage::STATUS_SKIPPED_PREF_OFF,
                body: $this->confirmationBody($booking),
                purpose: SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
            );
        }

        return $this->send(
            $booking,
            $this->confirmationBody($booking),
            SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
            'sms.booking_confirmation_failed',
        );
    }

    /**
     * Auto-SMS waiting patients when staff mark a session delayed.
     *
     * @return Collection<int, SmsMessage>
     */
    public function sendDoctorLateNotices(Booking $booking, int $delayMinutes, bool $staffTap = false): ?SmsMessage
    {
        $doctor = Doctor::resolveForBooking($booking);

        if (! $this->smsPrefAllows($doctor, Doctor::NOTIFY_DOCTOR_LATE, $staffTap)) {
            return $this->record(
                $booking,
                SmsMessage::STATUS_SKIPPED_PREF_OFF,
                body: $this->doctorLateBody($booking, $delayMinutes),
                purpose: SmsMessage::PURPOSE_DOCTOR_LATE,
            );
        }

        if ($already = $this->alreadySent($booking, SmsMessage::PURPOSE_DOCTOR_LATE)) {
            return $already;
        }

        return $this->send(
            $booking,
            $this->doctorLateBody($booking, $delayMinutes),
            SmsMessage::PURPOSE_DOCTOR_LATE,
            'sms.doctor_late_failed',
        );
    }

    /**
     * Staff-tapped cancellation SMS (vacation block or end-session).
     */
    public function sendCancellationNotice(Booking $booking, ?string $body = null): ?SmsMessage
    {
        $doctor = Doctor::resolveForBooking($booking);

        if ($doctor && ! $doctor->wantsSms(Doctor::NOTIFY_CANCELLATION)) {
            return $this->record(
                $booking,
                SmsMessage::STATUS_SKIPPED_PREF_OFF,
                body: $body ?? $this->cancellationBody($booking),
                purpose: SmsMessage::PURPOSE_CANCELLATION,
            );
        }

        return $this->send(
            $booking,
            $body ?? $this->cancellationBody($booking),
            SmsMessage::PURPOSE_CANCELLATION,
            'sms.cancellation_failed',
        );
    }

    /**
     * Staff-tapped prescription share SMS (48h signed link in the body).
     */
    public function sendPrescriptionNotice(Booking $booking, Prescription $prescription): ?SmsMessage
    {
        $doctor = Doctor::resolveForBooking($booking);
        $body = $this->prescriptionBody($booking, $prescription);

        if ($doctor && ! $doctor->wantsSms(Doctor::NOTIFY_PRESCRIPTION)) {
            return $this->record(
                $booking,
                SmsMessage::STATUS_SKIPPED_PREF_OFF,
                body: $body,
                purpose: SmsMessage::PURPOSE_PRESCRIPTION,
            );
        }

        return $this->send(
            $booking,
            $body,
            SmsMessage::PURPOSE_PRESCRIPTION,
            'sms.prescription_failed',
        );
    }

    /**
     * Automatic follow-up reminder, 3 days before the visit date.
     */
    public function sendFollowUpReminder(Booking $booking, VisitRecord $visit, Doctor $doctor): ?SmsMessage
    {
        $body = $this->followUpReminderBody($booking, $visit, $doctor);

        if (! $doctor->wantsSms(Doctor::NOTIFY_FOLLOW_UP)) {
            return $this->record(
                $booking,
                SmsMessage::STATUS_SKIPPED_PREF_OFF,
                body: $body,
                purpose: SmsMessage::PURPOSE_FOLLOW_UP,
            );
        }

        return $this->send(
            $booking,
            $body,
            SmsMessage::PURPOSE_FOLLOW_UP,
            'sms.follow_up_reminder_failed',
        );
    }

    public function followUpReminderBody(Booking $booking, VisitRecord $visit, Doctor $doctor): string
    {
        $date = $visit->follow_up_date?->format('j M Y') ?? '';
        $link = TenancyUrl::publicAbsolute((string) $booking->tenant_id, '/book');

        return implode(' ', array_filter([
            'Reminder: your follow-up with Dr '.$doctor->name.' is on '.$date.'.',
            'Reply or book: '.$link,
        ]));
    }

    public function topUp(Tenant $tenant, int $credits): void
    {
        if ($credits < 1) {
            throw new \InvalidArgumentException('SMS top-up must be at least 1 credit.');
        }

        $tenant->increment('sms_balance', $credits);
    }

    /**
     * Atomically spend credits. Returns false when the wallet cannot cover it.
     *
     * Credits are sold as "1 credit = 1 message", and `GsmText` keeps bodies
     * inside one segment so that stays true. The count is still a parameter
     * because one message genuinely cannot comply: the signed prescription
     * share link alone is longer than a segment. Billing what was actually
     * sent is what keeps a clinic's balance honest.
     */
    public function debitCredits(string $tenantId, int $credits): bool
    {
        $credits = max(1, $credits);

        return DB::table('tenants')
            ->where('id', $tenantId)
            ->where('sms_balance', '>=', $credits)
            ->decrement('sms_balance', $credits) > 0;
    }

    public function refundCredits(string $tenantId, int $credits): void
    {
        DB::table('tenants')->where('id', $tenantId)->increment('sms_balance', max(1, $credits));
    }

    public function debitOneCredit(string $tenantId): bool
    {
        return $this->debitCredits($tenantId, 1);
    }

    public function refundOneCredit(string $tenantId): void
    {
        $this->refundCredits($tenantId, 1);
    }

    public function confirmationBody(Booking $booking): string
    {
        $booking->loadMissing(['bookable']);

        // One lookup, used for both the letterhead name and the Live Queue
        // check below — this runs on the booking hot path and was fetching the
        // same row twice.
        $tenant = Tenant::find($booking->tenant_id);
        $clinic = $tenant?->displayName() ?? 'Clinic';
        $date = $booking->booking_date?->format('j M Y') ?? '';
        $session = '';
        $comeAround = null;
        $overflowPhrase = null;

        if ($booking->bookable instanceof ScheduleSession) {
            $booking->bookable->loadMissing(['doctor']);
            $doctor = $booking->bookable->doctor?->name;
            $sessionName = $booking->bookable->session_name;
            $session = trim(($doctor ? $doctor.' - ' : '').($sessionName ?? ''));

            if ($tenant?->hasLiveQueue()) {
                if ($booking->is_overflow) {
                    $overflowPhrase = app(PublishedComeAround::class)->overflowSmsPhrase($booking->bookable);
                } else {
                    $estimate = app(PublishedComeAround::class)->estimateForBooking($booking);
                    if ($estimate) {
                        $time = app(PublishedComeAround::class)->formatTimeForSms($estimate['shown_time']);
                        $comeAround = 'Come around '.$time;
                    }
                }
            }
        }

        $ticket = $this->ticketUrl($booking);

        // Keep ASCII/English so one credit = one GSM segment for v1.
        $name = $booking->patient_name;
        $parts = array_filter([
            $clinic.':',
            $name.' - serial '.$booking->serial_number,
            $date !== '' ? 'on '.$date : null,
            $comeAround,
            $overflowPhrase,
            $session !== '' ? '('.$session.')' : null,
            'Ticket: '.$ticket,
        ]);

        $body = implode(' ', $parts);

        if (GsmText::segments($body) > 1 && $session !== '') {
            $parts = array_filter([
                $clinic.':',
                $name.' - serial '.$booking->serial_number,
                $date !== '' ? 'on '.$date : null,
                $comeAround,
                $overflowPhrase,
                'Ticket: '.$ticket,
            ]);
            $body = implode(' ', $parts);
        }

        return GsmText::toSingleSegment($body);
    }

    public function doctorLateBody(Booking $booking, int $delayMinutes): string
    {
        $clinic = Tenant::find($booking->tenant_id)?->displayName() ?? 'Clinic';
        $ticket = $this->ticketUrl($booking);

        return implode(' ', array_filter([
            $clinic.':',
            'Doctor is delayed by '.$delayMinutes.' minutes.',
            $booking->patient_name.' - serial '.$booking->serial_number,
            'Ticket: '.$ticket,
        ]));
    }

    public function cancellationBody(Booking $booking): string
    {
        $clinic = Tenant::find($booking->tenant_id)?->displayName() ?? 'Clinic';
        $date = $booking->booking_date?->format('j M Y') ?? '';

        return implode(' ', array_filter([
            $clinic.':',
            $booking->patient_name.' - serial '.$booking->serial_number,
            $date !== '' ? 'on '.$date : null,
            'has been cancelled. Please contact us to rebook.',
        ]));
    }

    public function prescriptionBody(Booking $booking, Prescription $prescription): string
    {
        $clinic = Tenant::find($booking->tenant_id)?->displayName() ?? 'Clinic';
        $date = $booking->booking_date?->format('j M Y') ?? '';
        $link = $prescription->shareUrl();

        // "- view:" was dropped for a bare colon: with a long clinic name and a
        // long patient name the body lands within ~5 characters of the
        // 160-unit segment ceiling, and those 7 characters are the difference
        // between one credit and two. The link is self-evidently the thing to
        // tap. See PrescriptionShortLinkTest.
        return implode(' ', array_filter([
            $clinic.':',
            'Prescription for '.$booking->patient_name,
            $date !== '' ? 'from '.$date : null,
        ])).': '.$link;
    }

    /**
     * Login OTP for the platform patient account. ChamberQ pays — never debit
     * a doctor's SMS wallet, and never write a tenant-scoped sms_messages row.
     */
    public function sendPlatformOtp(string $phone, string $code): void
    {
        $body = GsmText::toSingleSegment(
            'ChamberQ code: '.$code.'. Valid 5 min. Do not share this code.'
        );

        if (! config('sms.enabled')) {
            Log::info('sms.platform_otp_skipped_disabled', [
                'phone' => $this->internationalPhone($phone),
            ]);

            return;
        }

        $to = $this->internationalPhone($phone);

        try {
            $this->gateway->send($to, $body);
        } catch (Throwable $e) {
            Log::warning('sms.platform_otp_failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Prescription-portal OTP. Debited from the clinic's prepaid SMS wallet.
     */
    public function sendPortalOtp(string $phone, string $code): void
    {
        $tenantId = (string) tenant('id');
        $clinic = tenant()?->displayName() ?? 'Clinic';
        $body = GsmText::toSingleSegment(
            $clinic.' code: '.$code.'. Valid 5 min. Do not share this code.'
        );
        $to = $this->internationalPhone($phone);

        if (! config('sms.enabled')) {
            Log::info('sms.portal_otp_skipped_disabled', [
                'tenant_id' => $tenantId,
                'phone' => $to,
            ]);

            return;
        }

        if (! $this->debitOneCredit($tenantId)) {
            throw ValidationException::withMessages([
                'code' => 'This clinic cannot send a verification SMS right now. Ask reception for help.',
            ]);
        }

        try {
            $this->gateway->send($to, $body);
        } catch (Throwable $e) {
            $this->refundOneCredit($tenantId);

            Log::warning('sms.portal_otp_failed', [
                'tenant_id' => $tenantId,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'booking_id' => null,
            'to' => $to,
            'body' => $body,
            'purpose' => SmsMessage::PURPOSE_PORTAL_OTP,
            'status' => SmsMessage::STATUS_SENT,
            'credits' => 1,
        ]);
    }

    public function internationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '88'.$digits;
        }

        if (! str_starts_with($digits, '88')) {
            return '88'.ltrim($digits, '8');
        }

        return $digits;
    }

    public function ticketUrl(Booking $booking): string
    {
        return TenancyUrl::publicAbsolute(
            (string) $booking->tenant_id,
            '/bookings/'.$booking->id,
        );
    }

    private function send(
        Booking $booking,
        string $body,
        string $purpose,
        string $failLogEvent,
    ): SmsMessage {
        // The one place every outgoing body passes through, and therefore the
        // only place worth enforcing "1 credit = 1 segment". Callers — including
        // `NotifySmsController`, which forwards free text a staff member typed —
        // may write whatever reads best; the shared WhatsApp copy keeps its real
        // dashes. See App\Support\GsmText.
        $body = GsmText::toSingleSegment($body);

        if (! config('sms.enabled')) {
            return $this->record($booking, SmsMessage::STATUS_SKIPPED_DISABLED, body: $body, purpose: $purpose);
        }

        $to = $this->internationalPhone((string) $booking->patient_phone);

        // Normally 1 — GsmText has already fitted the body to a segment. More
        // only when a link is longer than a segment and cannot be cut.
        $credits = GsmText::segments($body);
        $debited = $this->debitCredits((string) $booking->tenant_id, $credits);

        if (! $debited) {
            return $this->record($booking, SmsMessage::STATUS_SKIPPED_NO_BALANCE, $to, $body, purpose: $purpose);
        }

        try {
            $this->gateway->send($to, $body);

            return $this->record($booking, SmsMessage::STATUS_SENT, $to, $body, credits: $credits, purpose: $purpose);
        } catch (Throwable $e) {
            $this->refundCredits((string) $booking->tenant_id, $credits);

            Log::warning($failLogEvent, [
                'booking_id' => $booking->id,
                'tenant_id' => $booking->tenant_id,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            return $this->record(
                $booking,
                SmsMessage::STATUS_FAILED,
                $to,
                $body,
                credits: 0,
                error: $e->getMessage(),
                purpose: $purpose,
            );
        }
    }

    private function record(
        Booking $booking,
        string $status,
        ?string $to = null,
        ?string $body = null,
        int $credits = 0,
        ?string $error = null,
        string $purpose = SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
    ): SmsMessage {
        return SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'to' => $to ?? $this->internationalPhone((string) $booking->patient_phone),
            // Normalised here too, so a row logged on a path that never reached
            // the gateway (prefs off, wallet empty) still shows the exact text
            // that would have gone out. Idempotent for the sent path.
            'body' => GsmText::toSingleSegment((string) ($body ?? '')),
            'purpose' => $purpose,
            'status' => $status,
            'credits' => $credits,
            'error' => $error,
        ]);
    }
}
