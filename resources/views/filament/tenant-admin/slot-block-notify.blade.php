{{--
    Cancellation / delay notification list — shared by vacation mode (slot
    blocks), ending a live session early, and Mark Late WhatsApp hand-off.

    v1 has no WhatsApp API integration by design: WhatsApp links are tapped by
    staff. SMS uses the prepaid wallet via a staff tap (or auto for delay).

    @param \Illuminate\Support\Collection $bookings
    @param array<string, string> $messages  Optional per-booking override text,
           keyed by booking id. Defaults to Booking::whatsappLink()'s
           clinic-closed wording.
    @param string $stage  Doctor::NOTIFY_* stage for channel prefs
           (default: cancellation).
--}}
@php
    use App\Models\Doctor;

    $messages = $messages ?? [];
    $stage = $stage ?? Doctor::NOTIFY_CANCELLATION;
    $smsRouteName = match ($stage) {
        Doctor::NOTIFY_DOCTOR_LATE => 'bookings.sms.late',
        Doctor::NOTIFY_CANCELLATION => 'bookings.sms.cancellation',
        default => null,
    };
    $delayMinutes = (int) ($delayMinutes ?? 0);
@endphp
<div class="space-y-3" x-data="{
    sending: {},
    done: {},
    error: {},
    async sendSms(bookingId, url, payload) {
        this.sending[bookingId] = true;
        this.error[bookingId] = null;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                        || document.querySelector('input[name=_token]')?.value
                        || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload || {}),
            });
            const data = await res.json().catch(() => ({}));
            if (! res.ok) {
                this.error[bookingId] = data.message || @js(__('Could not send SMS'));
            } else if (data.status === 'sent') {
                this.done[bookingId] = true;
            } else if (data.status === 'skipped_no_balance') {
                this.error[bookingId] = @js(__('No SMS credits left'));
            } else if (data.status === 'skipped_pref_off') {
                this.error[bookingId] = @js(__('SMS is off for this doctor'));
            } else if (data.status === 'skipped_disabled') {
                this.error[bookingId] = @js(__('SMS is disabled'));
            } else {
                this.error[bookingId] = data.status || @js(__('Could not send SMS'));
            }
        } catch (e) {
            this.error[bookingId] = @js(__('Could not send SMS'));
        } finally {
            this.sending[bookingId] = false;
        }
    }
}">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('These patients need a message. Use WhatsApp and/or SMS according to each doctor\'s notification settings.') }}
    </p>

    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($bookings as $booking)
            @php
                $doctor = Doctor::resolveForBooking($booking);
                $showWa = $doctor?->wantsWhatsapp($stage) ?? ($stage === Doctor::NOTIFY_CANCELLATION);
                $showSms = $smsRouteName && ($doctor?->wantsPushSms($stage) ?? false);
                $waMessage = $messages[$booking->id] ?? null;
                $smsUrl = $smsRouteName
                    ? tenant_web_route($smsRouteName, $booking)
                    : null;
                $smsPayload = $stage === Doctor::NOTIFY_DOCTOR_LATE
                    ? ['delay_minutes' => $delayMinutes]
                    : ($waMessage ? ['message' => $waMessage] : []);
            @endphp
            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-950 dark:text-white">
                        #{{ $booking->serial_number }} — {{ $booking->patient_name }}
                    </p>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ $booking->patient_phone }}
                    </p>
                    <p
                        class="mt-1 text-xs text-danger-600 dark:text-danger-400"
                        x-show="error['{{ $booking->id }}']"
                        x-text="error['{{ $booking->id }}']"
                        x-cloak
                    ></p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($showWa && filled($booking->patient_phone))
                        <a
                            href="{{ $booking->whatsappLink($waMessage) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500"
                        >
                            {{ __('WhatsApp') }}
                        </a>
                    @endif

                    @if ($showSms && filled($booking->patient_phone))
                        <button
                            type="button"
                            class="rounded-lg bg-warning-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-warning-500 disabled:opacity-50"
                            x-bind:disabled="sending['{{ $booking->id }}'] || done['{{ $booking->id }}']"
                            x-on:click="sendSms(
                                @js($booking->id),
                                @js($smsUrl),
                                @js($smsPayload)
                            )"
                        >
                            <span x-show="! done['{{ $booking->id }}']" x-text="sending['{{ $booking->id }}'] ? @js(__('Sending…')) : @js(__('Send SMS'))"></span>
                            <span x-show="done['{{ $booking->id }}']" x-cloak>{{ __('Sent') }}</span>
                        </button>
                    @endif

                    @if (! $showWa && ! $showSms)
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No channel on for this doctor') }}
                        </span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
