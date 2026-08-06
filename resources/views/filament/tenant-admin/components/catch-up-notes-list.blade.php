<ul class="divide-y divide-gray-200 dark:divide-gray-700">
    @forelse ($bookings as $booking)
        <li class="flex items-center justify-between gap-4 py-3">
            <div class="min-w-0">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ $booking->patient_name }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Serial :n', ['n' => $booking->serial_number]) }}
                </div>
            </div>
            <x-filament::button
                size="sm"
                color="gray"
                wire:click="mountAction('catchUpBooking', { bookingId: '{{ $booking->id }}' })"
            >
                {{ __('Add notes') }}
            </x-filament::button>
        </li>
    @empty
        <li class="py-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Everyone seen today has notes.') }}
        </li>
    @endforelse
</ul>
