{{--
    Cancellation notification list — shared by vacation mode (slot blocks) and
    by ending a live session early.

    v1 has no WhatsApp API integration by design: these are links a member of
    staff taps, one per patient, so nothing is sent without a human deciding to
    send it.

    @param \Illuminate\Support\Collection $bookings
    @param array<string, string> $messages  Optional per-booking override text,
           keyed by booking id. Defaults to Booking::whatsappLink()'s
           clinic-closed wording.
--}}
@php($messages = $messages ?? [])
<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('These bookings were cancelled. Tap each patient to open WhatsApp with a prepared message.') }}
    </p>

    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($bookings as $booking)
            <li class="flex items-center justify-between gap-3 py-2">
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-950 dark:text-white">
                        #{{ $booking->serial_number }} — {{ $booking->patient_name }}
                    </p>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ $booking->patient_phone }}
                    </p>
                </div>

                <a
                    href="{{ $booking->whatsappLink($messages[$booking->id] ?? null) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500"
                >
                    {{ __('WhatsApp') }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
