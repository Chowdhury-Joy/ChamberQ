<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\Booking;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    public function __construct(
        private readonly SmsGateway $gateway,
    ) {}

    /**
     * Debit one prepaid credit and send a booking confirmation SMS.
     *
     * Booking always stays created — empty wallet or gateway failure only
     * affects the SMS row (and refunds the credit on hard send failure).
     */
    public function sendBookingConfirmation(Booking $booking): ?SmsMessage
    {
        if (! config('sms.enabled')) {
            return $this->record($booking, SmsMessage::STATUS_SKIPPED_DISABLED, body: $this->confirmationBody($booking));
        }

        $to = $this->internationalPhone($booking->patient_phone);
        $body = $this->confirmationBody($booking);

        $debited = $this->debitOneCredit((string) $booking->tenant_id);

        if (! $debited) {
            return $this->record($booking, SmsMessage::STATUS_SKIPPED_NO_BALANCE, $to, $body);
        }

        try {
            $this->gateway->send($to, $body);

            return $this->record($booking, SmsMessage::STATUS_SENT, $to, $body, credits: 1);
        } catch (Throwable $e) {
            $this->refundOneCredit((string) $booking->tenant_id);

            Log::warning('sms.booking_confirmation_failed', [
                'booking_id' => $booking->id,
                'tenant_id' => $booking->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return $this->record(
                $booking,
                SmsMessage::STATUS_FAILED,
                $to,
                $body,
                credits: 0,
                error: $e->getMessage(),
            );
        }
    }

    public function topUp(Tenant $tenant, int $credits): void
    {
        if ($credits < 1) {
            throw new \InvalidArgumentException('SMS top-up must be at least 1 credit.');
        }

        $tenant->increment('sms_balance', $credits);
    }

    /**
     * Atomically spend one credit. Returns false when the wallet is empty.
     */
    public function debitOneCredit(string $tenantId): bool
    {
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->where('sms_balance', '>=', 1)
            ->decrement('sms_balance') > 0;
    }

    public function refundOneCredit(string $tenantId): void
    {
        DB::table('tenants')->where('id', $tenantId)->increment('sms_balance');
    }

    public function confirmationBody(Booking $booking): string
    {
        $booking->loadMissing(['bookable']);

        $clinic = Tenant::find($booking->tenant_id)?->displayName() ?? 'Clinic';
        $date = $booking->booking_date?->format('j M Y') ?? '';
        $session = '';

        if ($booking->bookable instanceof ScheduleSession) {
            $booking->bookable->loadMissing(['doctor']);
            $doctor = $booking->bookable->doctor?->name;
            $sessionName = $booking->bookable->session_name;
            $session = trim(($doctor ? $doctor.' · ' : '').($sessionName ?? ''));
        }

        $ticket = $this->ticketUrl($booking);

        // Keep ASCII/English so one credit = one GSM segment for v1.
        $parts = array_filter([
            $clinic.':',
            $booking->patient_name.',',
            'serial '.$booking->serial_number,
            $date !== '' ? 'on '.$date : null,
            $session !== '' ? '('.$session.')' : null,
            'Ticket: '.$ticket,
        ]);

        return implode(' ', $parts);
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
        // Prefer the doctor's custom domain when one exists.
        $host = Domain::where('tenant_id', $booking->tenant_id)->value('domain');

        if ($host) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.$host.'/bookings/'.$booking->id;
        }

        // Platform path tenancy: /{tenant}/bookings/{uuid} on a central host.
        return route('path.bookings.show', [
            'tenant' => $booking->tenant_id,
            'booking' => $booking->id,
        ]);
    }

    private function record(
        Booking $booking,
        string $status,
        ?string $to = null,
        ?string $body = null,
        int $credits = 0,
        ?string $error = null,
    ): SmsMessage {
        return SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'to' => $to ?? $this->internationalPhone($booking->patient_phone),
            'body' => $body ?? '',
            'purpose' => SmsMessage::PURPOSE_BOOKING_CONFIRMATION,
            'status' => $status,
            'credits' => $credits,
            'error' => $error,
        ]);
    }
}
