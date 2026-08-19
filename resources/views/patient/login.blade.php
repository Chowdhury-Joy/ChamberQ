<x-patient.layout :title="__('Patient login')" :index="false">
    <section class="pf-hero pf-narrow">
        <h1>{{ __('Patient login') }}</h1>
        <p>{{ __('One login for every ChamberQ doctor. Booking itself does not need an account.') }}</p>
    </section>

    <div class="pf-panel">
        @if($errors->any())
            <p class="pf-error" role="alert">{{ $errors->first() }}</p>
        @endif

        @if(! $codeSent)
            <form method="POST" action="{{ route('patient.otp.send') }}" class="pf-form">
                @csrf
                <label for="phone">{{ __('Mobile number') }}</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $phone) }}" inputmode="numeric" autocomplete="tel" required placeholder="01712345678">
                <button type="submit" class="mk-btn mk-btn-primary">{{ __('Send code') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('patient.otp.verify') }}" class="pf-form">
                @csrf
                <input type="hidden" name="phone" value="{{ old('phone', $phone) }}">
                <p class="pf-lead">{{ __('We sent a code to :phone', ['phone' => $phone]) }}</p>
                <label for="code">{{ __('6-digit code') }}</label>
                <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                <button type="submit" class="mk-btn mk-btn-primary">{{ __('Verify') }}</button>
            </form>
            <form method="POST" action="{{ route('patient.otp.send') }}" class="pf-resend">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <button type="submit">{{ __('Send a new code') }}</button>
            </form>
        @endif
    </div>
</x-patient.layout>
