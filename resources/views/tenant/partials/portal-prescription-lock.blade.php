@php
    $rxGate = $rxGate ?? \App\Services\PortalPrescriptionLock::GATE_NONE;
    $rxOtpVerified = $rxOtpVerified ?? false;
    $rxErrors = $errors ?? null;
    $needsOtp = in_array($rxGate, [
        \App\Services\PortalPrescriptionLock::GATE_SETUP,
        \App\Services\PortalPrescriptionLock::GATE_UNLOCK,
    ], true) && ! $rxOtpVerified;
@endphp

@if($needsOtp)
    <div class="portal-rx-lock" style="margin-bottom: 2.5rem;">
        <h2>{{ __('Verify your mobile') }}</h2>
        <p class="portal-rx-lock-lead">
            @if(filled($phoneDisplay ?? null))
                {{ __('We will text a 6-digit code to :phone before you can set or change your prescription password.', ['phone' => $phoneDisplay]) }}
            @else
                {{ __('We will text a 6-digit code before you can set or change your prescription password.') }}
            @endif
        </p>

        @if(session('portal_otp_sent'))
            <form action="{{ tenant_web_route('patient.portal.rx-otp.verify', [], absolute: false) }}" method="POST" class="portal-rx-form">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <label class="sr-only" for="portal-rx-otp-code">{{ __('Code from SMS') }}</label>
                <input
                    id="portal-rx-otp-code"
                    class="form-control"
                    type="text"
                    name="code"
                    required
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="{{ __('6-digit code') }}"
                >
                <button type="submit" class="{{ $rxSetupButtonClass ?? 'solo-cta' }}">{{ __('Verify code') }}</button>
            </form>
            <form action="{{ tenant_web_route('patient.portal.rx-otp.send', [], absolute: false) }}" method="POST" class="portal-rx-form" style="margin-top: 0.5rem;">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <button type="submit" class="btn btn-ghost" style="min-height: 44px;">{{ __('Send again') }}</button>
            </form>
        @else
            <form action="{{ tenant_web_route('patient.portal.rx-otp.send', [], absolute: false) }}" method="POST" class="portal-rx-form">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <button type="submit" class="{{ $rxSetupButtonClass ?? 'solo-cta' }}">{{ __('Send verification code') }}</button>
            </form>
        @endif

        @if($rxGate === \App\Services\PortalPrescriptionLock::GATE_SETUP)
            <p class="portal-rx-hint">{{ __('Optional. Skip this if you like — your prescriptions stay visible with just this phone number.') }}</p>
        @endif

        @if($rxErrors?->has('code') || $rxErrors?->has('phone'))
            <p class="portal-error" role="alert">{{ $rxErrors->first('code') ?: $rxErrors->first('phone') }}</p>
        @endif
    </div>
@elseif($rxGate === \App\Services\PortalPrescriptionLock::GATE_SETUP)
    <div class="portal-rx-lock" style="margin-bottom: 2.5rem;">
        <h2>{{ __('Set a password for your prescriptions') }}</h2>
        <p class="portal-rx-lock-lead">
            {{ __('Optional. Skip this if you like — your prescriptions stay visible with just this phone number. Set a password only if you want them hidden next time.') }}
        </p>
        <form action="{{ tenant_web_route('patient.portal.rx-password', [], absolute: false) }}" method="POST" class="portal-rx-form">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">
            <label class="sr-only" for="portal-rx-password">{{ __('Password') }}</label>
            <input
                id="portal-rx-password"
                class="form-control"
                type="password"
                name="password"
                required
                minlength="{{ \App\Services\PortalPrescriptionLock::MIN_LENGTH }}"
                maxlength="72"
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
            >
            <label class="sr-only" for="portal-rx-password-confirm">{{ __('Confirm password') }}</label>
            <input
                id="portal-rx-password-confirm"
                class="form-control"
                type="password"
                name="password_confirmation"
                required
                minlength="{{ \App\Services\PortalPrescriptionLock::MIN_LENGTH }}"
                maxlength="72"
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
            >
            <button type="submit" class="{{ $rxSetupButtonClass ?? 'solo-cta' }}">{{ __('Save password') }}</button>
        </form>
        <p class="portal-rx-hint">{{ __('At least :count characters. Pick something you will remember on this phone.', ['count' => \App\Services\PortalPrescriptionLock::MIN_LENGTH]) }}</p>
        @if($rxErrors?->has('password') || $rxErrors?->has('phone'))
            <p class="portal-error" role="alert">{{ $rxErrors->first('password') ?: $rxErrors->first('phone') }}</p>
        @endif
    </div>
@elseif($rxGate === \App\Services\PortalPrescriptionLock::GATE_UNLOCK)
    <div class="portal-rx-lock" style="margin-bottom: 2.5rem;">
        <h2>{{ __('Your prescriptions') }}</h2>
        <p class="portal-rx-lock-lead">{{ __('Enter your prescription password') }}</p>
        <form action="{{ tenant_web_route('patient.portal.rx-unlock', [], absolute: false) }}" method="POST" class="portal-rx-form">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">
            <label class="sr-only" for="portal-rx-unlock">{{ __('Password') }}</label>
            <input
                id="portal-rx-unlock"
                class="form-control"
                type="password"
                name="password"
                required
                minlength="{{ \App\Services\PortalPrescriptionLock::MIN_LENGTH }}"
                maxlength="72"
                autocomplete="current-password"
                placeholder="{{ __('Password') }}"
            >
            <button type="submit" class="{{ $rxSetupButtonClass ?? 'solo-cta' }}">{{ __('Unlock prescriptions') }}</button>
        </form>
        <p class="portal-rx-hint">{{ __('Forgot this password? Ask reception to reset it.') }}</p>
        @if($rxErrors?->has('password'))
            <p class="portal-error" role="alert">{{ $rxErrors->first('password') }}</p>
        @endif
    </div>
@endif
