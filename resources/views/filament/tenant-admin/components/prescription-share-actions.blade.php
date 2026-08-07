{{--
    Print / send buttons shown while the visit is completed but the next patient
    has not been called yet — the window where the patient is still in the room.

    `whatsappLink()` is the existing human-tapped `wa.me` pattern (same as slot
    block cancellation notices); nothing is sent automatically.
--}}
@props(['booking', 'prescription'])

@if ($prescription)
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
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

        @if (filled($booking->patient_phone))
            <x-filament::button
                tag="a"
                href="{{ $booking->whatsappLink(__('Hello :name, here is your prescription from :date. You can view or print it here: :link', [
                    'name' => $booking->patient_name,
                    'date' => $booking->booking_date?->translatedFormat('j F Y'),
                    'link' => $prescription->shareUrl(),
                ])) }}"
                target="_blank"
                rel="noopener noreferrer"
                size="sm"
                color="success"
                icon="heroicon-o-paper-airplane"
            >
                {{ __('Send via WhatsApp') }}
            </x-filament::button>
        @endif
    </div>
@endif
