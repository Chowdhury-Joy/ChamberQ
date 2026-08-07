<style>
    .cu-list { display: flex; flex-direction: column; gap: 0.625rem; }
    .cu-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 0.75rem 1rem; border-radius: 0.625rem;
        background-color: var(--gray-50); border: 1px solid var(--gray-200);
    }
    .dark .cu-row { background-color: var(--gray-900); border-color: var(--gray-800); }
    .cu-name { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); }
    .dark .cu-name { color: var(--color-white); }
    .cu-serial {
        display: inline-flex; align-items: center; margin-top: 0.25rem;
        padding: 0.0625rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;
        background-color: var(--gray-100); color: var(--gray-600);
    }
    .dark .cu-serial { background-color: var(--gray-800); color: var(--gray-400); }
    .cu-empty {
        display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
        padding: 2.5rem 1rem; text-align: center;
    }
    .cu-empty-icon { width: 2.5rem; height: 2.5rem; color: var(--success-500); }
    .cu-empty-text { font-size: 0.875rem; color: var(--gray-500); }
    .dark .cu-empty-text { color: var(--gray-400); }
</style>

<div class="cu-list">
    @forelse ($bookings as $booking)
        <div class="cu-row">
            <div class="min-w-0">
                <div class="cu-name">{{ $booking->patient_name }}</div>
                <span class="cu-serial">{{ __('Serial :n', ['n' => $booking->serial_number]) }}</span>
            </div>
            <x-filament::button
                size="sm"
                color="gray"
                wire:click="replaceMountedAction('catchUpBooking', { bookingId: '{{ $booking->id }}' })"
            >
                {{ __('Add notes') }}
            </x-filament::button>
        </div>
    @empty
        <div class="cu-empty">
            <x-filament::icon icon="heroicon-o-check-circle" class="cu-empty-icon" />
            <p class="cu-empty-text">{{ __('Everyone seen today has notes.') }}</p>
        </div>
    @endforelse
</div>
