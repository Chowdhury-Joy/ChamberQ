<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('These are your personal prescribing shortcuts. Editing here does not change the shared medicine catalogue — only how this list appears when you write prescriptions.') }}
        </p>
    </x-filament::section>

    {{ $this->table }}

    {{--
        Packs live here rather than on the consult screen (owner decision):
        naming and building a set of medicines is preparation, not something a
        doctor does with a patient in the chair. The consult screen applies
        them; this page is where they are made and changed.
    --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Rx packs') }}</x-slot>
        <x-slot name="description">
            {{ __('A set of medicines you prescribe together. Apply one in a single tap while consulting.') }}
        </x-slot>

        @php($packs = $this->rxPacks)

        <div class="mb-3">
            <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="mountAction('createPack')">
                {{ __('New pack') }}
            </x-filament::button>
        </div>

        @if ($packs === [])
            <p class="text-sm text-gray-500">
                {{ __('No packs yet. Build one for the prescriptions you write most — it becomes one tap for every patient after.') }}
            </p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($packs as $pack)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ $pack['name'] }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ trans_choice(':count medicine|:count medicines', count($pack['items']), ['count' => count($pack['items'])]) }}
                                @if (filled($pack['items']))
                                    — {{ collect($pack['items'])->pluck('medicine_name')->filter()->take(4)->implode(', ') }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="mountAction('editPack', { packId: '{{ $pack['id'] }}' })"
                            >
                                {{ __('Edit') }}
                            </x-filament::button>
                            <x-filament::button
                                size="xs"
                                color="danger"
                                wire:click="mountAction('deletePack', { packId: '{{ $pack['id'] }}' })"
                            >
                                {{ __('Delete') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
