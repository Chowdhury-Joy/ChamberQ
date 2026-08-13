<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PatientOtpCode;
use App\Support\BdPhone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PatientOtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_SEND_PER_PHONE = 3;

    public const SEND_DECAY_SECONDS = 600;

    public const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsService $smsService,
    ) {}

    public function send(string $phone): void
    {
        $phone = $this->validatedPhone($phone);

        $sendKey = 'patient-otp-send:'.$phone;
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SEND_PER_PHONE)) {
            throw ValidationException::withMessages([
                'phone' => __('Please wait before requesting another code.'),
            ]);
        }

        RateLimiter::hit($sendKey, self::SEND_DECAY_SECONDS);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PatientOtpCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        PatientOtpCode::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->smsService->sendPlatformOtp($phone, $code);
    }

    public function verify(string $phone, string $code): PatientAccount
    {
        $phone = $this->validatedPhone($phone);
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6) {
            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired.'),
            ]);
        }

        $otp = PatientOtpCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired.'),
            ]);
        }

        if ($otp->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $otp->update(['consumed_at' => now()]);

            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired.'),
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired.'),
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        $account = PatientAccount::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => $this->guessName($phone)],
        );

        if (blank($account->name)) {
            $guessed = $this->guessName($phone);
            if (filled($guessed)) {
                $account->name = $guessed;
            }
        }

        $account->last_login_at = now();
        $account->save();

        return $account;
    }

    private function validatedPhone(string $phone): string
    {
        $normalized = BdPhone::normalize($phone);

        if (! BdPhone::isValid($normalized)) {
            throw ValidationException::withMessages([
                'phone' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
            ]);
        }

        return $normalized;
    }

    private function guessName(string $phone): ?string
    {
        $bookingName = Booking::withoutGlobalScopes()
            ->where('patient_phone', $phone)
            ->orderByDesc('created_at')
            ->value('patient_name');

        if (filled($bookingName)) {
            return (string) $bookingName;
        }

        $patientName = Patient::withoutGlobalScopes()
            ->where('phone', $phone)
            ->orderByDesc('updated_at')
            ->value('name');

        return filled($patientName) ? (string) $patientName : null;
    }
}
