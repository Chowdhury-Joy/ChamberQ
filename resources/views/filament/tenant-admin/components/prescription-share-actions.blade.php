{{--
    Print / send buttons shown while the visit is completed but the next patient
    has not been called yet — the window where the patient is still in the room.

    WhatsApp is the existing human-tapped `wa.me` pattern; SMS is staff-tapped
    against the prepaid wallet. Both are gated by the prescribing doctor's
    notify_channels.prescription prefs. A Google review link (Branding or
    Chamber) is appended when saved; paper-prescription chambers can send that
    link on its own.
--}}
@props(['booking', 'prescription'])

@php
    use App\Models\Doctor;
    use App\Support\VisitShareCopy;

    $doctor = $booking ? Doctor::resolveForBooking($booking) : null;
    $showWa = $doctor?->wantsWhatsapp(Doctor::NOTIFY_PRESCRIPTION) ?? true;
    $showSms = $doctor?->wantsSms(Doctor::NOTIFY_PRESCRIPTION) ?? false;
    $reviewUrl = $booking ? VisitShareCopy::reviewUrl($booking) : null;
    $hasShare = $prescription || $reviewUrl;
    $smsUrl = $prescription
        ? tenant_web_route('prescriptions.sms', $prescription)
        : ($booking ? tenant_web_route('bookings.sms.review', $booking) : null);
    $waMessage = $booking ? VisitShareCopy::whatsappMessage($booking, $prescription) : '';
@endphp

@if ($hasShare)
    <div
        style="display: flex; flex-wrap: wrap; gap: 0.5rem;"
        x-data="{
            sending: false,
            done: false,
            error: null,
            async sendSms() {
                this.sending = true;
                this.error = null;
                try {
                    const res = await fetch(@js($smsUrl), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                                || document.querySelector('input[name=_token]')?.value
                                || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: '{}',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (! res.ok) {
                        this.error = data.message || @js(__('Could not send SMS'));
                    } else if (data.status === 'sent') {
                        this.done = true;
                    } else if (data.status === 'skipped_no_balance') {
                        this.error = @js(__('No SMS credits left'));
                    } else if (data.status === 'skipped_pref_off') {
                        this.error = @js(__('SMS is off for this doctor'));
                    } else if (data.status === 'skipped_disabled') {
                        this.error = @js(__('SMS is disabled'));
                    } else {
                        this.error = data.status || @js(__('Could not send SMS'));
                    }
                } catch (e) {
                    this.error = @js(__('Could not send SMS'));
                } finally {
                    this.sending = false;
                }
            }
        }"
    >
        @if ($prescription)
            <x-filament::button
                tag="a"
                href="{{ tenant_web_route('prescriptions.print', $prescription) }}"
                target="_blank"
                size="sm"
                color="gray"
                icon="heroicon-m-printer"
            >
                {{ __('Print prescription') }}
            </x-filament::button>
        @endif

        @if ($showWa && $booking && filled($booking->patient_phone))
            <x-filament::button
                tag="a"
                href="{{ $booking->whatsappLink($waMessage) }}"
                target="_blank"
                rel="noopener noreferrer"
                size="sm"
                color="success"
                icon="heroicon-o-paper-airplane"
            >
                {{ $prescription ? __('Send via WhatsApp') : __('Send review via WhatsApp') }}
            </x-filament::button>
        @endif

        @if ($showSms && $booking && filled($booking->patient_phone) && $smsUrl)
            <x-filament::button
                type="button"
                size="sm"
                color="warning"
                icon="heroicon-o-device-phone-mobile"
                x-on:click="sendSms()"
                x-bind:disabled="sending || done"
            >
                <span x-show="! done" x-text="sending ? @js(__('Sending…')) : @js(__('Send SMS'))"></span>
                <span x-show="done" x-cloak>{{ __('Sent') }}</span>
            </x-filament::button>
            <p
                class="basis-full text-xs text-danger-600 dark:text-danger-400"
                x-show="error"
                x-text="error"
                x-cloak
            ></p>
        @endif
    </div>
@endif
