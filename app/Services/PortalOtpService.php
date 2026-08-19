<?php

namespace App\Services;

use App\Models\PortalOtpCode;
use App\Support\BdPhone;
use App\Support\PortalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PortalOtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_SEND_PER_PHONE = 3;

    public const SEND_DECAY_SECONDS = 600;

    public const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsService $smsService,
    ) {}

    public function send(Request $request, string $phone): void
    {
        $phone = $this->validatedPhone($phone);
        $this->assertPhoneMatchesSession($request, $phone);

        $sendKey = 'portal-otp-send:'.(string) tenant('id').':'.$phone;
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SEND_PER_PHONE)) {
            throw ValidationException::withMessages([
                'code' => __('Please wait before requesting another code.'),
            ]);
        }

        RateLimiter::hit($sendKey, self::SEND_DECAY_SECONDS);

        PortalOtpCode::query()
            ->where('tenant_id', tenant('id'))
            ->where('phone', $phone)
            ->whereNotNull('consumed_at')
            ->delete();

        PortalOtpCode::query()
            ->where('tenant_id', tenant('id'))
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PortalOtpCode::query()->create([
            'tenant_id' => tenant('id'),
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->smsService->sendPortalOtp($phone, $code);
    }

    public function verify(Request $request, string $phone, string $code): void
    {
        $phone = $this->validatedPhone($phone);
        $this->assertPhoneMatchesSession($request, $phone);

        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6) {
            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired.'),
            ]);
        }

        $otp = PortalOtpCode::query()
            ->where('tenant_id', tenant('id'))
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
        PortalSession::markOtpVerified($request, $phone);
    }

    public function assertVerifiedForPasswordChange(Request $request, string $phone): void
    {
        $phone = BdPhone::normalize($phone);

        if ($phone === '' || ! PortalSession::isOtpVerified($request, $phone)) {
            throw ValidationException::withMessages([
                'code' => __('Verify your mobile with the SMS code before changing your prescription password.'),
            ]);
        }
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

    private function assertPhoneMatchesSession(Request $request, string $normalizedPhone): void
    {
        $sessionPhone = PortalSession::phone($request);

        if ($sessionPhone === null || ! hash_equals($sessionPhone, $normalizedPhone)) {
            throw ValidationException::withMessages([
                'phone' => __('Look up your number on the portal first, then request a code.'),
            ]);
        }
    }
}
