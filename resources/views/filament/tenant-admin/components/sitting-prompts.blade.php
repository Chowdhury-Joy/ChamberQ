{{--
    Sticky sitting-prompt callouts — shared across Daily Roster, Live Queue,
    and Consult Screen. Uses lqc-banner classes from Live Queue Control CSS
    when that page's styles are not loaded; duplicates minimal rules here.
--}}
@props([
    'prompts',
    'canOperate' => false,
    'liveQueueUrl' => null,
    'selectedSessionId' => null,
    'context' => 'queue',
])

@if ($prompts->isNotEmpty())
    <style>
        .sit-prompt-banner {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 0.75rem; padding: 0.875rem 1.125rem;
            border: 1px solid var(--warning-300); border-radius: 0.75rem;
            background-color: var(--warning-50); color: var(--warning-900);
        }
        .dark .sit-prompt-banner { border-color: var(--warning-600); background-color: color-mix(in srgb, var(--warning-950) 60%, transparent); color: var(--warning-100); }
        .sit-prompt-text { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; }
        .sit-prompt-icon { width: 1.125rem; height: 1.125rem; flex-shrink: 0; color: var(--warning-600); }
        .dark .sit-prompt-icon { color: var(--warning-400); }
        .sit-prompt-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .sit-prompt-stack { display: flex; flex-direction: column; gap: 0.75rem; }
    </style>

    <div class="sit-prompt-stack">
        @foreach ($prompts as $prompt)
            <div class="sit-prompt-banner" wire:key="sitting-prompt-{{ $prompt['schedule_session_id'] }}">
                <span class="sit-prompt-text">
                    <x-filament::icon icon="heroicon-o-clock" class="sit-prompt-icon" />
                    {{ $prompt['message'] }}
                </span>

                @if ($canOperate && $context === 'queue')
                    <div class="sit-prompt-actions">
                        @if ($selectedSessionId !== null && (int) $selectedSessionId !== (int) $prompt['schedule_session_id'])
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="$set('selectedSessionId', {{ $prompt['schedule_session_id'] }})"
                            >
                                {{ __('Switch to :session', ['session' => $prompt['session_name']]) }}
                            </x-filament::button>
                        @else
                            @if (in_array($prompt['kind'], ['overdue', 'delay_expired'], true))
                                <x-filament::button
                                    size="sm"
                                    color="warning"
                                    wire:click="mountAction('markLate', { delay_minutes: {{ $prompt['suggested_delay_minutes'] ?? 30 }} })"
                                >
                                    {{ $prompt['kind'] === 'delay_expired' ? __('Add time') : __('Mark Late') }}
                                </x-filament::button>
                            @endif
                            @if ($prompt['kind'] === 'idle_after_start')
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="mountAction('pauseSession')"
                                >
                                    {{ __('Doctor stepped out') }}
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    size="sm"
                                    color="success"
                                    wire:click="mountStartSessionOrRun"
                                >
                                    {{ __('Start') }}
                                </x-filament::button>
                            @endif
                        @endif
                    </div>
                @elseif ($canOperate && $context === 'roster')
                    <div class="sit-prompt-actions">
                        @if (in_array($prompt['kind'], ['overdue', 'delay_expired'], true))
                            <x-filament::button
                                size="sm"
                                color="warning"
                                wire:click="mountTableAction('markLate', { schedule_session_id: {{ $prompt['schedule_session_id'] }}, delay_minutes: {{ $prompt['suggested_delay_minutes'] ?? 30 }} })"
                            >
                                {{ $prompt['kind'] === 'delay_expired' ? __('Add time') : __('Mark Late') }}
                            </x-filament::button>
                        @endif
                        @if ($liveQueueUrl)
                            <x-filament::button :href="$liveQueueUrl" tag="a" size="sm" color="success">
                                {{ __('Start in Live Queue') }}
                            </x-filament::button>
                        @endif
                    </div>
                @elseif ($canOperate && $context === 'consult')
                    <div class="sit-prompt-actions">
                        @if (in_array($prompt['kind'], ['overdue', 'delay_expired'], true))
                            <x-filament::button
                                size="sm"
                                color="warning"
                                wire:click="mountMarkLateForPrompt({{ $prompt['schedule_session_id'] }}, {{ $prompt['suggested_delay_minutes'] ?? 30 }})"
                            >
                                {{ $prompt['kind'] === 'delay_expired' ? __('Add time') : __('Mark Late') }}
                            </x-filament::button>
                        @endif
                        @if ($prompt['kind'] === 'idle_after_start')
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="mountAction('doctorSteppedOut')"
                            >
                                {{ __('Doctor stepped out') }}
                            </x-filament::button>
                        @else
                            <x-filament::button
                                size="sm"
                                color="success"
                                wire:click="startSessionFromPrompt({{ $prompt['schedule_session_id'] }})"
                            >
                                {{ __('Start') }}
                            </x-filament::button>
                        @endif
                    </div>
                @elseif ($liveQueueUrl)
                    <x-filament::button :href="$liveQueueUrl" tag="a" size="sm" color="warning">
                        {{ __('Open Live Queue') }}
                    </x-filament::button>
                @endif
            </div>
        @endforeach
    </div>
@endif
