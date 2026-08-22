<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('These are your personal prescribing shortcuts. Editing here does not change the shared medicine catalogue — only how this list appears when you write prescriptions.') }}
        </p>
    </x-filament::section>

    {{ $this->table }}

    {{--
        Advice and History chips. Same idea as the medicine list above: what the
        pad offers in one tap is the doctor's own vocabulary, not a fixed five
        lines shipped by us. The chips that came with the app can be edited or
        taken off; taken-off ones are listed at the bottom so nothing the doctor
        used to have disappears without a way back.
    --}}
    @foreach ([
        [
            'kind' => 'advice',
            'heading' => __('Advice chips'),
            'description' => __('One tap writes the line into the Advice box. The button is in your language; the line printed for the patient can be in Bangla.'),
            'chips' => $this->adviceChips,
            'create' => 'createAdviceChip',
            'edit' => 'editAdviceChip',
            'remove' => 'removeAdviceChip',
            'restore' => 'restoreAdviceChip',
            'empty' => __('No advice chips. Add the lines you tell patients most.'),
        ],
        [
            'kind' => 'history',
            'heading' => __('History chips'),
            'description' => __('The past-history toggles on the pad — HTN, DM, and whatever else you see all day.'),
            'chips' => $this->historyChips,
            'create' => 'createHistoryChip',
            'edit' => 'editHistoryChip',
            'remove' => 'removeHistoryChip',
            'restore' => 'restoreHistoryChip',
            'empty' => __('No history chips. Add the conditions you ask about most.'),
        ],
    ] as $group)
        <x-filament::section>
            <x-slot name="heading">{{ $group['heading'] }}</x-slot>
            <x-slot name="description">{{ $group['description'] }}</x-slot>

            @php($visible = collect($group['chips'])->reject(fn (array $chip) => $chip['is_hidden']))
            @php($hidden = collect($group['chips'])->filter(fn (array $chip) => $chip['is_hidden']))

            <div class="mb-3">
                <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="mountAction('{{ $group['create'] }}')">
                    {{ __('Add chip') }}
                </x-filament::button>
            </div>

            @if ($visible->isEmpty())
                <p class="text-sm text-gray-500">{{ $group['empty'] }}</p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($visible as $chip)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="chip-{{ $group['kind'] }}-{{ $chip['id'] }}">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $chip['label'] }}
                                    @if (! $chip['is_primary'])
                                        <span class="text-xs font-normal text-gray-500">— {{ __('behind More…') }}</span>
                                    @endif
                                </div>
                                @if ($chip['text'] !== $chip['label'])
                                    <div class="text-xs text-gray-500">{{ $chip['text'] }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    wire:click="mountAction('{{ $group['edit'] }}', { chipId: '{{ $chip['id'] }}' })"
                                >
                                    {{ __('Edit') }}
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="danger"
                                    wire:click="mountAction('{{ $group['remove'] }}', { chipId: '{{ $chip['id'] }}' })"
                                >
                                    {{ __('Remove') }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($hidden->isNotEmpty())
                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                    <p class="mb-2 text-xs font-medium text-gray-500">{{ __('Removed') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($hidden as $chip)
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:key="chip-hidden-{{ $group['kind'] }}-{{ $chip['id'] }}"
                                wire:click="mountAction('{{ $group['restore'] }}', { chipId: '{{ $chip['id'] }}' })"
                            >
                                {{ $chip['label'] }} — {{ __('Restore') }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endforeach

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
