@if ($items !== [])
    <div class="cs-copy-last">
        <p class="cs-copy-last__text">{{ __('Same medicines as the last visit?') }}</p>
        <x-filament::button
            type="button"
            size="sm"
            color="gray"
            x-on:click="Livewire.dispatch('copy-last-prescription', { items: @js($items) })"
        >
            {{ __('Copy from last visit') }}
        </x-filament::button>
    </div>
@endif
