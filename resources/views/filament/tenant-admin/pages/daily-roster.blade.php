<x-filament-panels::page wire:poll.15s>
    <style>
        .roster-date-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .roster-date-field {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        .roster-date-label {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        .roster-date-input {
            min-width: 11rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            background: var(--color-white);
            color: var(--gray-950);
        }
        .dark .roster-date-input {
            background: var(--gray-800);
            border-color: var(--gray-700);
            color: var(--color-white);
        }
        .roster-date-showing {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        .roster-date-showing strong {
            color: var(--gray-950);
            font-weight: 600;
        }
        .dark .roster-date-showing strong {
            color: var(--color-white);
        }
        .roster-date-today {
            appearance: none;
            border: 1px solid var(--gray-300);
            background: transparent;
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        .dark .roster-date-today {
            border-color: var(--gray-700);
            color: var(--gray-200);
        }
        @media (max-width: 640px) {
            .roster-date-input { min-width: 100%; width: 100%; }
            .roster-date-field { flex: 1 1 100%; }
        }
    </style>

    <div class="roster-date-bar" wire:key="roster-date-{{ $this->rosterDateString() }}">
        <div class="roster-date-field">
            <label for="roster-date" class="roster-date-label">{{ __('Date') }}</label>
            <input id="roster-date" type="date" wire:model.live="rosterDate" class="roster-date-input" />
        </div>
        @if (! $this->isViewingToday())
            <button type="button" class="roster-date-today" wire:click="jumpToToday">
                {{ __('Today') }}
            </button>
        @endif
        <div class="roster-date-showing">
            {{ __('Showing') }}
            <strong>{{ $this->rosterDateLabel() }}</strong>
        </div>
    </div>

    @if ($this->isViewingToday() && tenant()?->hasLiveQueue() && (auth()->user()?->canManageQueue() || auth()->user()?->canOperateQueueControls()))
        @include('filament.tenant-admin.components.staff-buzz-card')
    @endif

    @if ($this->isViewingToday())
        @include('filament.tenant-admin.components.sitting-prompts', [
            'prompts' => $this->sittingPrompts,
            'canOperate' => auth()->user()?->canManageQueue() ?? false,
            'liveQueueUrl' => \App\Filament\TenantAdmin\Pages\LiveQueueControl::getUrl(),
            'selectedSessionId' => null,
            'context' => 'roster',
        ])
    @endif

    <div style="margin-top: {{ ($this->isViewingToday() && $this->sittingPrompts->isNotEmpty()) ? '1rem' : '0' }}">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
