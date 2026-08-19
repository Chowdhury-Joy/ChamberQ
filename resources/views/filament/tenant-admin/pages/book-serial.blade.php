<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('For a phone call or the front desk when the patient is not standing in the queue. Pick the date first. New Walk-In on Daily Roster is still for today only.') }}
    </p>

    <form wire:submit="book" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" size="lg">
                {{ __('Book serial') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
