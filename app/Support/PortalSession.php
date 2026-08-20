<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Patient portal phone lives in the session — never in the URL after lookup.
 */
final class PortalSession
{
    public const OTP_VERIFIED_TTL_SECONDS = 900;

    public static function phoneSessionKey(): string
    {
        return 'portal.phone.'.(string) tenant('id');
    }

    public static function storePhone(Request $request, string $normalizedPhone): void
    {
        $request->session()->put(self::phoneSessionKey(), $normalizedPhone);
    }

    public static function phone(Request $request): ?string
    {
        $phone = $request->session()->get(self::phoneSessionKey());

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public static function clearPhone(Request $request): void
    {
        $request->session()->forget(self::phoneSessionKey());
    }

    public static function portalUrl(): string
    {
        return tenant_web_url('/portal');
    }

    public static function otpVerifiedSessionKey(string $normalizedPhone): string
    {
        return 'portal.rx_otp_verified.'.(string) tenant('id').'.'.$normalizedPhone;
    }

    public static function markOtpVerified(Request $request, string $normalizedPhone): void
    {
        $request->session()->put(
            self::otpVerifiedSessionKey($normalizedPhone),
            now()->timestamp,
        );
    }

    public static function isOtpVerified(Request $request, string $normalizedPhone): bool
    {
        $verifiedAt = $request->session()->get(self::otpVerifiedSessionKey($normalizedPhone));

        if (! is_int($verifiedAt) && ! is_numeric($verifiedAt)) {
            return false;
        }

        return now()->timestamp - (int) $verifiedAt <= self::OTP_VERIFIED_TTL_SECONDS;
    }

    public static function clearOtpVerified(Request $request, string $normalizedPhone): void
    {
        $request->session()->forget(self::otpVerifiedSessionKey($normalizedPhone));
    }
}
