<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('These are your personal prescribing shortcuts. Editing here does not change the shared medicine catalogue — only how this list appears when you write prescriptions.') }}
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
