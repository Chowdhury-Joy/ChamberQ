<x-filament-panels::page wire:poll.15s>
    {{--
        This panel has no custom Filament theme build, so the only CSS that
        reaches the browser is Filament's own bundle — it does NOT contain
        general Tailwind utilities (`grid-cols-3`, `gap-6`, `text-sm`, `w-full`
        and friends are all absent from public/css/filament/filament/app.css).
        Every layout rule this page needs therefore lives here as real CSS.
        Dark mode is class-based (`.dark` on <html>).
    --}}
    <style>
        [x-cloak] { display: none !important; }

        .lqc-stack { display: flex; flex-direction: column; gap: 1.5rem; }
        .lqc-stack-sm { display: flex; flex-direction: column; gap: 0.75rem; }
        .lqc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        @media (min-width: 900px) { .lqc-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
        @media (min-width: 1280px) { .lqc-grid { grid-template-columns: minmax(0, 22rem) minmax(0, 1fr); } }
        @media (min-width: 900px) { .lqc-side { position: sticky; top: 1rem; } }

        .lqc-row { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .lqc-field { flex: 1 1 18rem; max-width: 36rem; }
        .lqc-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: rgb(63 63 70); }
        .dark .lqc-label { color: rgb(212 212 216); }

        .lqc-muted { font-size: 0.875rem; color: rgb(113 113 122); }
        .lqc-xs { font-size: 0.75rem; color: rgb(113 113 122); }
        .dark .lqc-muted, .dark .lqc-xs { color: rgb(161 161 170); }
        .lqc-eyebrow { font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(113 113 122); }
        .dark .lqc-eyebrow { color: rgb(161 161 170); }

        /* Buttons that are the main thing to press fill their card. */
        .lqc-actions { display: flex; flex-direction: column; gap: 0.5rem; }
        .lqc-actions .fi-btn { width: 100%; justify-content: center; }
        /* Same Complete visit treatment as Consult Screen: solid green, white type. */
        .cs-complete-visit-btn.fi-btn.fi-color-success {
            background-color: var(--success-600, #16a34a);
            color: #fff;
            --text: #fff;
            --hover-text: #fff;
            --dark-text: #fff;
            --dark-hover-text: #fff;
        }
        .cs-complete-visit-btn.fi-btn.fi-color-success:hover {
            background-color: var(--success-500, #22c55e);
            color: #fff;
        }
        .cs-complete-visit-btn.fi-btn.fi-color-success > .fi-icon,
        .cs-complete-visit-btn.fi-btn.fi-color-success svg {
            color: #fff;
        }
        .lqc-btn-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }

        .lqc-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: rgb(228 228 231); border: 1px solid rgb(228 228 231); border-radius: 0.75rem; overflow: hidden; }
        @media (min-width: 900px) { .lqc-stats { grid-template-columns: repeat(var(--lqc-stat-count, 4), minmax(0, 1fr)); } }
        .dark .lqc-stats { background: rgb(63 63 70); border-color: rgb(63 63 70); }
        .lqc-stat { background: rgb(255 255 255); padding: 0.75rem 1rem; }
        .dark .lqc-stat { background: rgb(24 24 27); }
        .lqc-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: rgb(24 24 27); font-variant-numeric: tabular-nums; }
        .dark .lqc-stat-value { color: rgb(244 244 245); }

        .lqc-callbox { padding: 1.25rem; border: 1px solid rgb(228 228 231); border-radius: 0.75rem; background: rgb(250 250 250); display: flex; flex-direction: column; gap: 0.75rem; }
        .dark .lqc-callbox { border-color: rgb(39 39 42); background: rgb(24 24 27); }
        .lqc-callbox-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }

        .lqc-serial { font-size: 3.25rem; line-height: 1; font-weight: 800; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; color: rgb(24 24 27); }
        .dark .lqc-serial { color: rgb(244 244 245); }
        .lqc-name { font-size: 1.125rem; font-weight: 600; color: rgb(39 39 42); }
        .dark .lqc-name { color: rgb(228 228 231); }
        .lqc-elapsed { font-variant-numeric: tabular-nums; font-weight: 600; }
        .lqc-elapsed-over { color: rgb(185 28 28); }
        .dark .lqc-elapsed-over { color: rgb(248 113 113); }

        .lqc-dot { display: inline-block; position: relative; width: 0.5rem; height: 0.5rem; margin-inline-end: 0.375rem; }
        .lqc-dot span { position: absolute; inset: 0; border-radius: 9999px; background: currentColor; }
        .lqc-dot span:first-child { animation: lqc-ping 1.4s cubic-bezier(0, 0, 0.2, 1) infinite; }
        @keyframes lqc-ping { 75%, 100% { transform: scale(2.2); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .lqc-dot span:first-child { animation: none; } }

        /* The row the chamber is announcing right now must be findable at a glance. */
        .fi-ta-row-called > td { background-color: rgb(254 252 232) !important; }
        .fi-ta-row-called > td:first-child { box-shadow: inset 3px 0 0 rgb(234 179 8); }
        .dark .fi-ta-row-called > td { background-color: rgb(66 44 8) !important; }
        .fi-ta-row-in-chamber > td { background-color: rgb(240 253 244) !important; }
        .fi-ta-row-in-chamber > td:first-child { box-shadow: inset 3px 0 0 rgb(34 197 94); }
        .dark .fi-ta-row-in-chamber > td { background-color: rgb(12 46 27) !important; }

        .lqc-empty { padding: 2rem 0; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; }
        .lqc-empty-title { font-size: 1.125rem; font-weight: 600; color: rgb(24 24 27); }
        .dark .lqc-empty-title { color: rgb(244 244 245); }
        .lqc-empty p { max-width: 28rem; margin: 0; }

        .lqc-cards { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.75rem; }
        @media (min-width: 768px) { .lqc-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .lqc-session-card { display: block; width: 100%; text-align: start; padding: 1rem; border: 1px solid rgb(228 228 231); border-radius: 0.75rem; background: rgb(255 255 255); font-weight: 500; color: rgb(24 24 27); cursor: pointer; transition: border-color .15s, background-color .15s; }
        .lqc-session-card:hover { border-color: rgb(161 161 170); background: rgb(250 250 250); }
        .dark .lqc-session-card { border-color: rgb(63 63 70); background: rgb(24 24 27); color: rgb(244 244 245); }
        .dark .lqc-session-card:hover { border-color: rgb(113 113 122); background: rgb(39 39 42); }

        .lqc-banner {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 0.75rem; padding: 0.875rem 1.125rem;
            border: 1px solid var(--warning-300); border-radius: 0.75rem;
            background-color: var(--warning-50); color: var(--warning-900);
        }
        .dark .lqc-banner { border-color: var(--warning-600); background-color: color-mix(in srgb, var(--warning-950) 60%, transparent); color: var(--warning-100); }
        .lqc-banner-text { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; }
        .lqc-banner-icon { width: 1.125rem; height: 1.125rem; flex-shrink: 0; color: var(--warning-600); }
        .dark .lqc-banner-icon { color: var(--warning-400); }
    </style>

    @php
        $catchUpCount = $this->catchUpCount;
        $canRecordNotes = auth()->user()?->canRecordVisitNotes() ?? false;
    @endphp

    <div class="lqc-stack cq-freeze-queue">

        @if (tenant()?->hasLiveQueue() && (auth()->user()?->canManageQueue() || auth()->user()?->canOperateQueueControls()))
            @include('filament.tenant-admin.components.staff-buzz-card')
        @endif

        @include('filament.tenant-admin.components.sitting-prompts', [
            'prompts' => $this->sittingPrompts,
            'canOperate' => auth()->user()?->canOperateQueueControls() ?? false,
            'liveQueueUrl' => null,
            'selectedSessionId' => $this->selectedSessionId,
        ])

        @if ($canRecordNotes && $catchUpCount > 0)
            <div class="lqc-banner">
                <span class="lqc-banner-text">
                    <x-filament::icon icon="heroicon-o-clock" class="lqc-banner-icon" />
                    {{ __(':count patients today without notes', ['count' => $catchUpCount]) }}
                </span>
                <x-filament::button
                    size="sm"
                    color="warning"
                    wire:click="mountAction('catchUpNotes')"
                >
                    {{ 'Fill in now' }}
                </x-filament::button>
            </div>
        @endif

        {{-- Session picker --}}
        <x-filament::section>
            @if($this->sessions->isEmpty())
                <div class="lqc-empty">
                    <div class="lqc-empty-title">{{ __('No sessions scheduled for today') }}</div>
                    <p class="lqc-muted">
                        {{ __('Live Queue only lists sessions that run on :day. Add or edit a schedule that includes today, then return here.', ['day' => now()->translatedFormat('l')]) }}
                    </p>
                    @if(auth()->user()?->canManageOps())
                        <x-filament::button
                            href="{{ \App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource::getUrl('index') }}"
                            tag="a"
                            color="primary"
                            icon="heroicon-m-calendar-days"
                        >
                            {{ 'Manage schedules' }}
                        </x-filament::button>
                    @endif
                </div>
            @else
                <div class="lqc-row">
                    <div class="lqc-field">
                        <label for="lqc-session" class="lqc-label">
                            {{ __('Session for today (:date)', ['date' => now()->translatedFormat('l, j F Y')]) }}
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="selectedSessionId" id="lqc-session">
                                <option value="">{{ __('Choose a session…') }}</option>
                                @foreach($this->sessions as $id => $label)
                                    <option value="{{ $id }}" @selected($this->selectedSessionId == $id)>{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    @if($this->selectedSessionId && app()->isLocal())
                        <x-filament::button wire:click="addMockPatients" color="gray" size="sm" icon="heroicon-m-user-plus">
                            {{ 'Add sample patients' }}
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </x-filament::section>

        @if($this->selectedSessionId)
            @php
                $liveSession = $this->activeLiveSession;
                $bookings = $this->bookings;
                $status = $liveSession?->status ?? 'scheduled';
                $current = $liveSession?->currentBooking;
                $stats = $this->queueStats;
                $nextWaiting = $bookings->whereIn('status', ['waiting', 'skipped'])->sortBy('serial_number')->first();
                $statCount = 3 + ($stats['finishes_at'] ? 1 : 0) + ($stats['no_show'] > 0 ? 1 : 0);
            @endphp

            {{-- "How many are left and when do we finish" — asked all session long. --}}
            @if(in_array($status, ['active', 'paused'], true))
                <div class="lqc-stats" style="--lqc-stat-count: {{ $statCount }};">
                    <div class="lqc-stat">
                        <div class="lqc-eyebrow">{{ __('Waiting') }}</div>
                        <div class="lqc-stat-value">{{ $stats['waiting'] }}</div>
                    </div>
                    <div class="lqc-stat">
                        <div class="lqc-eyebrow">{{ __('Seen') }}</div>
                        <div class="lqc-stat-value">{{ $stats['done'] }}</div>
                    </div>
                    @if($stats['no_show'] > 0)
                        <div class="lqc-stat">
                            <div class="lqc-eyebrow">{{ __('No-show') }}</div>
                            <div class="lqc-stat-value">{{ $stats['no_show'] }}</div>
                        </div>
                    @endif
                    <div class="lqc-stat">
                        <div class="lqc-eyebrow">{{ __('Avg consult') }}</div>
                        <div class="lqc-stat-value">{{ $stats['avg_minutes'] }}m</div>
                        <div class="lqc-xs">{{ $stats['avg_is_observed'] ? __('measured today') : __('from schedule') }}</div>
                    </div>
                    @if($stats['finishes_at'])
                        <div class="lqc-stat">
                            <div class="lqc-eyebrow">{{ __('Finishes about') }}</div>
                            <div class="lqc-stat-value">{{ $stats['finishes_at']->format('g:i a') }}</div>
                            <div class="lqc-xs">{{ __('at today\'s pace') }}</div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="lqc-grid">

                {{-- Controls & current call --}}
                <div class="lqc-stack lqc-side">
                    <x-filament::section>
                        <x-slot name="heading">{{ __('Session status') }}</x-slot>
                        <x-slot name="headerEnd">
                            @if($status === 'scheduled')
                                <x-filament::badge color="gray">{{ __('Not started') }}</x-filament::badge>
                            @elseif($status === 'delayed')
                                <x-filament::badge color="warning">{{ __('Delayed :minutes m', ['minutes' => $liveSession->delay_minutes]) }}</x-filament::badge>
                            @elseif($status === 'active')
                                <x-filament::badge color="success">
                                    <span class="lqc-dot"><span></span><span></span></span>{{ __('Live') }}
                                </x-filament::badge>
                            @elseif($status === 'paused')
                                <x-filament::badge color="warning" icon="heroicon-m-pause">{{ __('Paused') }}</x-filament::badge>
                            @elseif($status === 'completed')
                                <x-filament::badge color="info">{{ __('Finished') }}</x-filament::badge>
                            @elseif($status === 'cancelled')
                                <x-filament::badge color="danger">{{ __('Cancelled') }}</x-filament::badge>
                            @endif
                        </x-slot>

                        <div class="lqc-stack-sm">
                            @if(in_array($status, ['scheduled', 'delayed'], true))
                                @if($status === 'delayed')
                                    {{-- x-filament::callout renders `description`/`footer` only —
                                         a default slot is silently dropped. --}}
                                    <x-filament::callout color="warning" icon="heroicon-m-clock">
                                        <x-slot name="description">
                                            {{ __('Patients have been told the doctor is running :minutes minutes late. Starting now clears the yellow banner; the clock follows when you actually begin.', ['minutes' => $liveSession->delay_minutes]) }}
                                        </x-slot>
                                    </x-filament::callout>
                                @endif
                                <div class="lqc-actions">
                                    <x-filament::button wire:click="mountStartSessionOrRun" color="success" icon="heroicon-m-play" size="lg">
                                        {{ 'Start live session' }}
                                    </x-filament::button>
                                </div>
                                <p class="lqc-muted">
                                    {{ __(':count patients booked. Starting calls the first serial straight away.', ['count' => $bookings->whereIn('status', ['waiting', 'skipped'])->count()]) }}
                                </p>
                            @endif

                            @if($status === 'paused')
                                <x-filament::callout color="warning" icon="heroicon-m-pause" heading="{{ $liveSession->pause_reason ?: __('Session paused') }}">
                                    <x-slot name="description">
                                        @if($liveSession->pauseEndsAt())
                                            {{ __('Expected back around :time (:minutes minute break).', ['time' => $liveSession->pauseEndsAt()->format('g:i a'), 'minutes' => $liveSession->estimated_pause_minutes]) }}
                                        @else
                                            {{ __('No end time was estimated for this break.') }}
                                        @endif
                                        @if($stats['waiting'] > 0)
                                            {{ __(':count patients are still waiting.', ['count' => $stats['waiting']]) }}
                                        @endif
                                    </x-slot>
                                </x-filament::callout>
                                <div class="lqc-actions">
                                    <x-filament::button wire:click="mountAction('resumeSession')" color="success" icon="heroicon-m-play" size="lg">
                                        {{ "He's back" }}
                                    </x-filament::button>
                                </div>
                            @endif

                            @if(in_array($status, ['completed', 'cancelled'], true))
                                <p class="lqc-muted">
                                    @if($status === 'cancelled')
                                        {{ __('This session was cancelled') }}{{ $liveSession->cancellation_reason ? ' — '.$liveSession->cancellation_reason : '' }}. {{ __('All active bookings were cancelled.') }}
                                    @else
                                        {{ __('Session finished') }}{{ $liveSession->completed_at ? ' '.__('at :time', ['time' => $liveSession->completed_at->format('g:i a')]) : '' }}. {{ __(':count patients seen.', ['count' => $bookings->where('status', 'completed')->count()]) }}
                                    @endif
                                </p>
                            @endif

                            @if($status === 'active')
                                <div class="lqc-callbox">
                                    @if($current)
                                        <div class="lqc-callbox-head">
                                            <div>
                                                <div class="lqc-eyebrow">
                                                    @if($current->status === 'in_chamber')
                                                        Now serving
                                                    @elseif($current->status === 'completed')
                                                        Visit completed
                                                    @else
                                                        Now calling
                                                    @endif
                                                </div>
                                                <div class="lqc-serial">#{{ $current->serial_number }}</div>
                                            </div>
                                            @if($current->status === 'called')
                                                {{-- Short enough not to truncate on a phone. --}}
                                                <x-filament::badge color="warning" icon="heroicon-m-bell-alert">{{ __('Not arrived') }}</x-filament::badge>
                                            @elseif($current->status === 'completed')
                                                <x-filament::badge color="info" icon="heroicon-m-check-circle">{{ __('Done') }}</x-filament::badge>
                                            @else
                                                <x-filament::badge color="success" icon="heroicon-m-check-circle">{{ __('In chamber') }}</x-filament::badge>
                                            @endif
                                        </div>

                                        <div class="lqc-name">{{ $current->patient_name }}</div>

                                        @php
                                            $since = $current->status === 'in_chamber'
                                                ? $current->in_chamber_at
                                                : ($liveSession->current_called_at ?? $current->called_at);
                                            $timeoutSeconds = $current->status === 'called' ? $liveSession->callTimeoutSeconds() : null;
                                        @endphp
                                        {{-- No running timer once the visit is closed — nothing is elapsing. --}}
                                        @if($since && $current->status !== 'completed')
                                            {{-- wire:key forces a fresh DOM node (and a fresh Alpine init)
                                                 when the patient or their status changes, so the timer
                                                 restarts from the right moment instead of morphing. --}}
                                            <div
                                                class="lqc-muted"
                                                wire:key="lqc-elapsed-{{ $current->id }}-{{ $current->status }}"
                                                x-data="{
                                                    startedAt: Date.parse(@js($since->toIso8601String())),
                                                    // Correct for clock skew between server and this device,
                                                    // or a mis-set desk PC shows nonsense elapsed times.
                                                    skew: Date.now() - Date.parse(@js(now()->toIso8601String())),
                                                    timeoutSeconds: @js($timeoutSeconds),
                                                    text: '',
                                                    over: false,
                                                    timer: null,
                                                    init() {
                                                        const tick = () => {
                                                            const secs = Math.max(0, Math.floor((Date.now() - this.skew - this.startedAt) / 1000));
                                                            this.text = secs < 60
                                                                ? secs + 's'
                                                                : Math.floor(secs / 60) + 'm ' + String(secs % 60).padStart(2, '0') + 's';
                                                            this.over = this.timeoutSeconds !== null && secs >= this.timeoutSeconds;
                                                        };
                                                        tick();
                                                        this.timer = setInterval(tick, 1000);
                                                    },
                                                    destroy() { clearInterval(this.timer) },
                                                }"
                                            >
                                                {{-- Not "In chamber" — the badge above already says that. --}}
                                                {{ $timeoutSeconds !== null ? __('Called') : __('With the doctor for') }}
                                                <span class="lqc-elapsed" :class="{ 'lqc-elapsed-over': over }" x-text="text"></span>
                                                <span x-show="over" x-cloak>{{ __('— no response yet') }}</span>
                                            </div>
                                        @endif

                                        <div class="lqc-actions">
                                            @php
                                                $handoff = app(\App\Services\StationsHandoffService::class);
                                                $canBookIntervention = $handoff->actorMaySend(auth()->user())
                                                    && $handoff->canSendVisit($current);
                                                $canMoveIntervention = $handoff->actorMaySend(auth()->user())
                                                    && $handoff->canMove($current);
                                                $canSendToCounseling = $handoff->actorMaySend(auth()->user())
                                                    && $handoff->canSendToCounseling($current);
                                            @endphp
                                            @if($current->status === 'called')
                                                <x-filament::button wire:click="patientArrived" color="success" icon="heroicon-m-check" size="lg" class="cq-offline-queue-allowed" data-cq-queue-action="patient_arrived">
                                                    {{ 'Patient arrived' }}
                                                </x-filament::button>
                                                <x-filament::button
                                                    wire:click="skipPatient"
                                                    color="{{ $liveSession->isCallTimedOut() ? 'danger' : 'gray' }}"
                                                    icon="heroicon-m-forward"
                                                    class="cq-offline-queue-allowed"
                                                    data-cq-queue-action="skip"
                                                >
                                                    @if($current->skip_count >= 2)
                                                        {{ 'No response — mark no-show' }}
                                                    @else
                                                        {{ 'No response — skip ('.($current->skip_count + 1).' of 2)' }}
                                                    @endif
                                                </x-filament::button>
                                                <p class="lqc-xs">
                                                    @if($current->skip_count >= 2)
                                                        {{ __('They have missed both calls — this removes them from today\'s queue.') }}
                                                    @else
                                                        {{ __('Moves them down the queue; called again after #:serial.', ['serial' => $current->serial_number + 1]) }}
                                                    @endif
                                                </p>
                                            @elseif($current->status === 'completed')
                                                {{-- The patient is still in the room: hand over the
                                                     prescription before calling the next one in. --}}
                                                @include('filament.tenant-admin.components.call-next-nudge', ['booking' => $current])
                                                @include('filament.tenant-admin.components.prescription-share-actions', [
                                                    'booking' => $current,
                                                    'prescription' => $current->visitRecord?->prescription,
                                                ])
                                                <x-filament::button wire:click="callNextPatientOnly" color="primary" icon="heroicon-m-megaphone" size="lg" class="cq-offline-queue-allowed" data-cq-queue-action="call_next">
                                                    {{ 'Call next patient' }}
                                                </x-filament::button>
                                                @if($canBookIntervention)
                                                    <x-filament::button wire:click="mountAction('bookCurrentIntervention')" color="warning" icon="heroicon-m-arrow-right-circle">
                                                        {{ __('Book intervention') }}
                                                    </x-filament::button>
                                                @elseif($canMoveIntervention)
                                                    <x-filament::button wire:click="mountAction('moveCurrentIntervention')" color="gray" icon="heroicon-m-calendar-days">
                                                        {{ __('Move intervention') }}
                                                    </x-filament::button>
                                                @endif
                                                @if($canSendToCounseling)
                                                    <x-filament::button wire:click="mountAction('sendCurrentToCounseling')" color="success" icon="heroicon-m-chat-bubble-left-right">
                                                        {{ __('Send to counseling') }}
                                                    </x-filament::button>
                                                @endif
                                            @else
                                                <x-filament::button class="cs-complete-visit-btn cq-offline-queue-allowed" wire:click="completeVisit" color="success" icon="heroicon-m-check-badge" size="lg" data-cq-queue-action="complete_without_advance">
                                                    {{ 'Complete visit' }}
                                                </x-filament::button>
                                                @if($canBookIntervention)
                                                    <x-filament::button wire:click="mountAction('bookCurrentIntervention')" color="warning" icon="heroicon-m-arrow-right-circle">
                                                        {{ __('Book intervention') }}
                                                    </x-filament::button>
                                                @elseif($canMoveIntervention)
                                                    <x-filament::button wire:click="mountAction('moveCurrentIntervention')" color="gray" icon="heroicon-m-calendar-days">
                                                        {{ __('Move intervention') }}
                                                    </x-filament::button>
                                                @endif
                                                @if($canSendToCounseling)
                                                    <x-filament::button wire:click="mountAction('sendCurrentToCounseling')" color="success" icon="heroicon-m-chat-bubble-left-right">
                                                        {{ __('Send to counseling') }}
                                                    </x-filament::button>
                                                @endif
                                            @endif
                                        </div>
                                    @else
                                        <div class="lqc-eyebrow">{{ __('No active call') }}</div>
                                        @if($nextWaiting)
                                            <div class="lqc-serial">#{{ $nextWaiting->serial_number }}</div>
                                            <div class="lqc-name">{{ $nextWaiting->patient_name }}</div>
                                            <div class="lqc-actions">
                                                <x-filament::button wire:click="callNextPatientOnly" color="primary" icon="heroicon-m-megaphone" size="lg" class="cq-offline-queue-allowed" data-cq-queue-action="call_next">
                                                    {{ 'Call #'.$nextWaiting->serial_number }}
                                                </x-filament::button>
                                            </div>
                                        @else
                                            <p class="lqc-muted">
                                                {{ __('Nobody left waiting. Use Session actions → Finish / End session to close today.') }}
                                            </p>
                                            <div class="lqc-actions">
                                                <x-filament::button color="gray" icon="heroicon-m-megaphone" size="lg" disabled>
                                                    {{ __('No one waiting') }}
                                                </x-filament::button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-filament::section>

                    @php
                        // Stable path — no date. Bookmark once; it always shows today for this session.
                        $screenUrl = \App\Support\TenancyUrl::screenBookmarkUrl(
                            (string) tenant('id'),
                            (int) $this->selectedSessionId,
                        );
                        $chamberId = \App\Models\ScheduleSession::find($this->selectedSessionId)?->chamber_id;
                        $chamberScreenUrl = $chamberId && (tenant()?->hasStations() ?? false)
                            ? \App\Support\TenancyUrl::chamberScreenBookmarkUrl(
                                (string) tenant('id'),
                                (int) $chamberId,
                            )
                            : null;
                    @endphp
                    <x-filament::section>
                        <div class="lqc-stack-sm" x-data="{ copied: false, copy() { navigator.clipboard.writeText(@js($screenUrl)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                            <div class="lqc-name">{{ __('Waiting-room TV screen') }}</div>
                            <p class="lqc-muted">
                                {{ __('Bookmark this link on the TV once — it always shows today\'s queue for this session. No need to paste a new link each morning.') }}
                            </p>
                            @if ($chamberScreenUrl)
                                <p class="lqc-muted">
                                    {{ __('For every room in this chamber, bookmark the all-rooms TV instead.') }}
                                </p>
                            @endif
                            <div class="lqc-btn-row">
                                <x-filament::button :href="$screenUrl" tag="a" target="_blank" color="gray" icon="heroicon-m-arrow-top-right-on-square" size="sm">
                                    {{ 'Open screen' }}
                                </x-filament::button>
                                @if ($chamberScreenUrl)
                                    <x-filament::button :href="$chamberScreenUrl" tag="a" target="_blank" color="gray" icon="heroicon-m-squares-2x2" size="sm">
                                        {{ 'All rooms TV' }}
                                    </x-filament::button>
                                @endif
                                <x-filament::button x-on:click="copy()" color="gray" icon="heroicon-m-clipboard" size="sm">
                                    <span x-text="copied ? @js('Link copied') : @js('Copy link')">Copy link</span>
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                {{-- Queue table --}}
                <div>
                    {{ $this->table }}
                </div>
            </div>
        @elseif($this->sessions->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">{{ __('Today\'s sessions') }}</x-slot>
                <x-slot name="description">{{ __('Pick the session you are running to open its live queue.') }}</x-slot>
                <div class="lqc-cards">
                    @foreach($this->sessions as $id => $label)
                        <button type="button" class="lqc-session-card" wire:click="$set('selectedSessionId', '{{ $id }}')">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />

    @php
        $announceUsesVoice = tenant()?->usesCallVoice() ?? false;
        $announceBaseUrl = rtrim(public_asset('audio/announce'), '/');
    @endphp

    @if($announceUsesVoice)
        <div
            x-data="{
                base: @js($announceBaseUrl),
                locale: @js(tenant()?->call_announce_locale ?? 'en'),
                blocked: false,
                play(payload) {
                    const detail = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
                    const raw = detail.serial ?? payload?.serial ?? payload;
                    const name = detail.name ?? '';
                    const n = parseInt(raw, 10);
                    const el = this.$refs.audio;
                    if (! el || ! Number.isFinite(n) || n < 1 || n > 99) return;

                    el.muted = false;
                    el.pause();
                    el.onended = () => { this.speakName(name); };
                    el.src = this.base + '/number-' + n + '.wav';
                    el.play()
                        .then(() => { this.blocked = false })
                        .catch(() => { this.blocked = true });
                },
                speakName(name) {
                    const text = String(name || '').trim();
                    if (! text || ! ('speechSynthesis' in window)) return;

                    try { window.speechSynthesis.cancel(); } catch (e) {}

                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = this.locale === 'bn' ? 'bn-BD' : 'en-US';
                    utterance.rate = 0.95;

                    const voices = window.speechSynthesis.getVoices() || [];
                    const prefix = this.locale === 'bn' ? 'bn' : 'en';
                    const voice = voices.find(v => String(v.lang || '').toLowerCase().startsWith(prefix))
                        || voices.find(v => String(v.lang || '').toLowerCase().startsWith('en'))
                        || voices[0];
                    if (voice) utterance.voice = voice;

                    window.speechSynthesis.speak(utterance);
                },
                unlock() {
                    const el = this.$refs.audio;
                    if (! el) return;
                    // Play-then-pause inside the click gesture is what actually
                    // lifts the autoplay block; muted so staff hear nothing now.
                    el.muted = true;
                    el.src = this.base + '/number-1.wav';
                    el.play().then(() => {
                        el.pause();
                        el.currentTime = 0;
                        el.muted = false;
                        this.blocked = false;
                    }).catch(() => {});

                    if ('speechSynthesis' in window) {
                        try { window.speechSynthesis.getVoices(); } catch (e) {}
                    }
                },
            }"
            x-on:queue-called.window="play($event.detail)"
        >
            <audio x-ref="audio" preload="auto" style="display: none"></audio>

            {{-- Browsers block audio until the tab has been interacted with.
                 Failing silently made staff believe the chamber was announcing. --}}
            <div x-cloak x-show="blocked" style="margin-top: 1.5rem">
                <x-filament::callout color="warning" icon="heroicon-m-speaker-x-mark" heading="Call announcements are muted">
                    <x-slot name="description">
                        Your browser blocked the announcement audio. Tap Enable sound once and every call will be announced here.
                    </x-slot>
                    <x-slot name="controls">
                        <x-filament::button x-on:click="unlock()" color="warning" size="sm" icon="heroicon-m-speaker-wave">
                            Enable sound
                        </x-filament::button>
                    </x-slot>
                </x-filament::callout>
            </div>
        </div>

        @script
        <script>
            $wire.on('queue-called', (payload) => {
                window.dispatchEvent(new CustomEvent('queue-called', { detail: payload }));
            });
        </script>
        @endscript
    @endif
</x-filament-panels::page>
