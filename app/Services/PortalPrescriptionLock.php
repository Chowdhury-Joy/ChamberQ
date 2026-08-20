<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PortalPhonePassword;
use App\Support\BdPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Support\PortalSession;

/**
 * Optional privacy lock for the *patient* portal only.
 *
 * Serials stay phone-only. Prescriptions stay phone-only until the patient
 * chooses a password for this clinic. Doctors, Consult Screen, and
 * cross-chamber shared history never read this table.
 */
class PortalPrescriptionLock
{
    public const GATE_NONE = 'none';

    /** Visited; no password chosen — pads stay open; offer optional setup. */
    public const GATE_SETUP = 'setup';

    public const GATE_UNLOCK = 'unlock';

    public const GATE_OPEN = 'open';

    public const MIN_LENGTH = 6;

    public const MAX_LENGTH = 72;

    public function gate(Request $request, string $phone): string
    {
        $normalized = BdPhone::normalize($phone);

        if ($normalized === '' || ! (tenant()?->hasPrescription() ?? false) || ! $this->hasCompletedVisit($normalized)) {
            return self::GATE_NONE;
        }

        if (! $this->hasPassword($normalized)) {
            return self::GATE_SETUP;
        }

        if ($this->isUnlocked($request, $normalized)) {
            return self::GATE_OPEN;
        }

        return self::GATE_UNLOCK;
    }

    public function maySetPassword(string $phone): bool
    {
        $normalized = BdPhone::normalize($phone);

        return $normalized !== ''
            && (tenant()?->hasPrescription() ?? false)
            && $this->hasCompletedVisit($normalized);
    }

    public function prescriptionsVisible(Request $request, string $phone): bool
    {
        return $this->gate($request, $phone) !== self::GATE_UNLOCK;
    }

    public function hasCompletedVisit(string $phone): bool
    {
        $variants = BdPhone::lookupVariants($phone);

        return Booking::query()
            ->whereIn('patient_phone', $variants)
            ->where('status', 'completed')
            ->exists();
    }

    public function hasPassword(string $phone): bool
    {
        return $this->recordFor($phone) !== null;
    }

    public function isUnlocked(Request $request, string $phone): bool
    {
        $normalized = BdPhone::normalize($phone);
        $unlocked = (string) $request->session()->get($this->sessionKey($normalized), '');

        return $unlocked !== '' && hash_equals($unlocked, $this->unlockToken($normalized));
    }

    public function setPassword(Request $request, string $phone, string $password): void
    {
        $normalized = BdPhone::normalize($phone);

        app(PortalOtpService::class)->assertVerifiedForPasswordChange($request, $normalized);

        if (! $this->maySetPassword($normalized)) {
            throw ValidationException::withMessages([
                'phone' => __('You can set a prescription password after you have seen the doctor at least once.'),
            ]);
        }

        if ($this->hasPassword($normalized)) {
            throw ValidationException::withMessages([
                'password' => __('A prescription password is already set for this number. Enter it to unlock.'),
            ]);
        }

        PortalPhonePassword::query()->create([
            'phone' => $normalized,
            'password' => Hash::make($password),
        ]);

        $this->markUnlocked($request, $normalized);
    }

    public function unlock(Request $request, string $phone, string $password): void
    {
        $normalized = BdPhone::normalize($phone);

        app(PortalOtpService::class)->assertVerifiedForPasswordChange($request, $normalized);

        $rateKey = $this->unlockRateKey($normalized);

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($rateKey) / 60);

            throw ValidationException::withMessages([
                'password' => __('Too many wrong attempts. Try again in :minutes minutes.', [
                    'minutes' => max(1, $minutes),
                ]),
            ]);
        }

        $record = $this->recordFor($normalized);

        if (! $record || ! Hash::check($password, $record->password)) {
            RateLimiter::hit($rateKey, 900);

            throw ValidationException::withMessages([
                'password' => __('That password does not match.'),
            ]);
        }

        RateLimiter::clear($rateKey);

        $this->markUnlocked($request, $normalized);
    }

    public function lock(Request $request, string $phone): void
    {
        $request->session()->forget($this->sessionKey(BdPhone::normalize($phone)));
    }

    public function clearPassword(string $phone): void
    {
        $record = $this->recordFor($phone);

        $record?->delete();
    }

    public function portalRedirect(): string
    {
        return PortalSession::portalUrl();
    }

    private function recordFor(string $phone): ?PortalPhonePassword
    {
        $normalized = BdPhone::normalize($phone);

        if ($normalized === '') {
            return null;
        }

        return PortalPhonePassword::query()->where('phone', $normalized)->first();
    }

    private function markUnlocked(Request $request, string $normalizedPhone): void
    {
        $request->session()->put($this->sessionKey($normalizedPhone), $this->unlockToken($normalizedPhone));
    }

    private function sessionKey(string $normalizedPhone): string
    {
        return 'portal_rx_unlocked.'.(string) tenant('id').'.'.$normalizedPhone;
    }

    private function unlockToken(string $normalizedPhone): string
    {
        return hash('sha256', (string) tenant('id').'|'.$normalizedPhone.'|rx');
    }

    private function unlockRateKey(string $normalizedPhone): string
    {
        return 'portal-rx-unlock:'.(string) tenant('id').':'.$normalizedPhone;
    }
}
