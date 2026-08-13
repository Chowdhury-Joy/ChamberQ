<x-filament-panels::page wire:poll.3s>
    @php
        $booking = $this->currentBooking;
        $patient = $this->currentPatient;
        $liveSession = $this->runningLiveSession;
        $tenant = tenant();
        $isClinic = $tenant?->isClinic() ?? false;
        $visitHistory = $this->visitHistory;
        $sharedVisitHistory = $this->sharedVisitHistory;
        $sharedClinicalWarnings = $this->sharedClinicalWarnings;
        $lastVisitRecord = $this->lastVisitRecord;
        $hasSharedWarnings = collect($sharedClinicalWarnings)->flatten()->isNotEmpty();
        $canViewNotes = auth()->user()?->canViewVisitNotes() ?? false;
        $canWriteNotes = auth()->user()?->canRecordVisitNotes() ?? false;
        $displayName = $patient?->name ?? $booking?->patient_name;
        $initials = $displayName
            ? \Illuminate\Support\Str::of($displayName)->explode(' ')->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('')
            : '?';
    @endphp

    {{--
        This panel ships Filament's precompiled stylesheet, so custom visual
        elements below are built with Filament's own CSS variables (colors,
        radii) rather than assumed arbitrary Tailwind utilities.
    --}}
    <style>
        .cs-empty { padding: 3.5rem 1.5rem; text-align: center; }
        .cs-empty-icon {
            width: 3rem; height: 3rem; margin: 0 auto 1rem; color: var(--gray-300);
        }
        .dark .cs-empty-icon { color: var(--gray-700); }
        .cs-empty-title { font-size: 1.0625rem; font-weight: 600; color: var(--gray-950); margin-bottom: 0.375rem; }
        .dark .cs-empty-title { color: var(--color-white); }
        .cs-empty-body { font-size: 0.875rem; color: var(--gray-500); max-width: 30rem; margin: 0 auto; }
        .cs-empty-hint { font-size: 0.875rem; color: var(--gray-500); max-width: 30rem; margin: 0.75rem auto 0; }

        .cs-stack { display: flex; flex-direction: column; gap: 1.25rem; }

        .cs-layout { display: flex; flex-direction: column; gap: 1.25rem; }
        .cs-layout__side,
        .cs-layout__main { display: flex; flex-direction: column; gap: 1.25rem; }
        @media (max-width: 767px) {
            .cs-layout__side,
            .cs-layout__main { display: contents; }
            .cs-block--header { order: 1; }
            .cs-block--warnings { order: 2; }
            .cs-block--write { order: 3; }
            .cs-block--done { order: 4; }
            .cs-block--last-visit { order: 5; }
            .cs-block--past-visits { order: 6; }
            .cs-block--shared-visits { order: 6; }
            .cs-block--lab { order: 7; }
        }

        .cs-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
        .cs-identity { display: flex; align-items: center; gap: 1rem; min-width: 0; }
        .cs-avatar {
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            width: 3.25rem; height: 3.25rem; border-radius: 9999px;
            background-color: var(--primary-50); color: var(--primary-700);
            font-size: 1.125rem; font-weight: 700; letter-spacing: 0.02em;
        }
        .dark .cs-avatar { background-color: color-mix(in srgb, var(--primary-500) 18%, transparent); color: var(--primary-300); }
        .cs-name { font-size: 1.375rem; font-weight: 700; color: var(--gray-950); line-height: 1.25; }
        .dark .cs-name { color: var(--color-white); }
        .cs-pills { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.5rem; }
        .cs-pill {
            display: inline-flex; align-items: center; gap: 0.3125rem;
            padding: 0.1875rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;
            background-color: var(--gray-100); color: var(--gray-700);
        }
        .dark .cs-pill { background-color: var(--gray-800); color: var(--gray-300); }

        .cs-summary {
            flex-shrink: 0; min-width: 0; padding: 0.75rem 1rem; border-radius: 0.625rem;
            background-color: var(--gray-50); border: 1px solid var(--gray-200);
            width: 100%;
        }
        .dark .cs-summary { background-color: var(--gray-900); border-color: var(--gray-800); }
        .cs-summary-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--gray-700); }
        .dark .cs-summary-row { color: var(--gray-300); }
        .cs-summary-row + .cs-summary-row { margin-top: 0.375rem; }
        .cs-summary-icon { width: 1rem; height: 1rem; flex-shrink: 0; color: var(--gray-400); }
        .cs-summary-strong { font-weight: 600; color: var(--gray-950); }
        .dark .cs-summary-strong { color: var(--color-white); }
        .cs-first-visit { font-size: 0.8125rem; font-weight: 500; color: var(--gray-600); }
        .dark .cs-first-visit { color: var(--gray-400); }

        .cs-done-row {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;
        }
        /* Status line itself lives in the shared call-next-nudge partial. */

        .cs-write-row {
            display: flex; flex-direction: column; align-items: stretch; gap: 1rem;
        }
        .cs-primary-btn { width: 100%; min-height: 2.75rem; }
        .cs-sticky-actions {
            position: sticky; bottom: 0; z-index: 20;
            margin-top: 1rem; padding: 0.75rem 0 calc(0.75rem + env(safe-area-inset-bottom));
            background: linear-gradient(to top, var(--gray-50), color-mix(in srgb, var(--gray-50) 92%, transparent));
            border-top: 1px solid var(--gray-200);
        }
        .dark .cs-sticky-actions {
            background: linear-gradient(to top, var(--gray-950), color-mix(in srgb, var(--gray-950) 92%, transparent));
            border-color: var(--gray-800);
        }
        .cs-sticky-actions__btn { width: 100%; min-height: 2.75rem; }
        /* Filament paints success as a pale chip with dark type when white
           fails WCAG on green-600. Complete visit must read as a filled go
           button: white label and icon on solid green. */
        .cs-complete-visit-btn.fi-btn.fi-color-success {
            background-color: var(--success-600);
            color: #fff;
            --text: #fff;
            --hover-text: #fff;
            --dark-text: #fff;
            --dark-hover-text: #fff;
        }
        .cs-complete-visit-btn.fi-btn.fi-color-success:hover {
            background-color: var(--success-500);
            color: #fff;
        }
        .cs-complete-visit-btn.fi-btn.fi-color-success > .fi-icon,
        .cs-complete-visit-btn.fi-btn.fi-color-success svg {
            color: #fff;
        }
        /* The sticky bar repeats Patient arrived / Complete visit / Call next in a
           thumb-reachable strip, and it is the only copy shown on phones — the page
           header's own actions are the same three, under the same conditions, so
           leaving both on screen showed the doctor two Complete visit buttons.
           Desktop is the reverse: the sticky bar is hidden and the header stands. */
        @media (max-width: 767px) {
            .fi-header-actions-ctn { display: none; }
        }
        .cs-media-chip audio { height: 2rem; width: 100%; max-width: none; }
        @media (min-width: 768px) {
            .cs-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
            .cs-layout__side,
            .cs-layout__main { display: flex; flex-direction: column; gap: 1.25rem; }
            .cs-block--header,
            .cs-block--warnings,
            .cs-block--write,
            .cs-block--done,
            .cs-block--last-visit,
            .cs-block--past-visits,
            .cs-block--lab { order: unset; }
            .cs-write-row { flex-direction: row; align-items: center; justify-content: space-between; }
            /* Once the button is sized to its content it is a flex item next to a
               summary that can grow, so without these it gets squeezed and
               "Write prescription" breaks onto a second line. */
            .cs-primary-btn { width: auto; flex-shrink: 0; white-space: nowrap; }
            .cs-sticky-actions { display: none; }
            .cs-summary { width: auto; max-width: 14rem; }
        }
        @media (min-width: 1280px) {
            .cs-layout__side { position: sticky; top: 1rem; }
        }
        .cs-write-summary { min-width: 0; }
        .cs-write-title { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); }
        .dark .cs-write-title { color: var(--color-white); }
        .cs-write-hint { font-size: 0.8125rem; color: var(--gray-500); margin-top: 0.125rem; }
        .cs-write-detail { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.375rem; }
        .cs-write-chip {
            display: inline-flex; align-items: center;
            padding: 0.125rem 0.5rem; border-radius: 9999px;
            font-size: 0.75rem; font-weight: 500;
            background-color: var(--primary-50); color: var(--primary-700);
        }
        .dark .cs-write-chip {
            background-color: color-mix(in srgb, var(--primary-500) 20%, transparent);
            color: var(--primary-300);
        }

        .cs-warning-grid { display: grid; grid-template-columns: 1fr; gap: 0.875rem; }
        .cs-warning-item {
            padding: 0.75rem 0.875rem; border-radius: 0.625rem;
            background-color: color-mix(in srgb, var(--warning-100) 65%, transparent);
            border: 1px solid var(--warning-200);
        }
        .dark .cs-warning-item { background-color: color-mix(in srgb, var(--warning-950) 45%, transparent); border-color: var(--warning-800); }
        .cs-warning-label {
            font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--warning-800);
        }
        .dark .cs-warning-label { color: var(--warning-300); }
        .cs-warning-value { font-size: 0.875rem; color: var(--warning-950); margin-top: 0.1875rem; }
        .dark .cs-warning-value { color: var(--warning-100); }

        .cs-section-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%; }
        .cs-followup-pill {
            display: inline-flex; align-items: center; gap: 0.3125rem;
            padding: 0.1875rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;
            background-color: var(--primary-50); color: var(--primary-700);
        }
        .dark .cs-followup-pill { background-color: color-mix(in srgb, var(--primary-500) 20%, transparent); color: var(--primary-300); }

        .cs-field + .cs-field { margin-top: 0.875rem; }
        .cs-field-label {
            font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--gray-500);
        }
        .dark .cs-field-label { color: var(--gray-500); }
        .cs-field-value { font-size: 0.875rem; color: var(--gray-800); margin-top: 0.1875rem; white-space: pre-wrap; }
        .dark .cs-field-value { color: var(--gray-200); }
        .cs-diagnosis { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); margin-top: 0.1875rem; }
        .dark .cs-diagnosis { color: var(--color-white); }

        .cs-media-row { display: flex; flex-wrap: wrap; gap: 0.625rem; margin-top: 1rem; }
        .cs-media-chip {
            display: inline-flex; align-items: center; gap: 0.4375rem; padding: 0.375rem 0.75rem;
            border-radius: 0.5rem; background-color: var(--gray-50); border: 1px solid var(--gray-200);
            font-size: 0.8125rem; color: var(--gray-700);
        }
        .dark .cs-media-chip { background-color: var(--gray-900); border-color: var(--gray-800); color: var(--gray-300); }
        .cs-media-chip a { color: var(--primary-600); font-weight: 500; text-decoration: none; }
        .dark .cs-media-chip a { color: var(--primary-400); }
        .cs-media-chip a:hover { text-decoration: underline; }
        .cs-media-chip audio { height: 2rem; max-width: 14rem; }

        .cs-muted { font-size: 0.875rem; color: var(--gray-500); }
        .dark .cs-muted { color: var(--gray-500); }

        .cs-visits { display: flex; flex-direction: column; gap: 0.625rem; }
        .cs-visit-card {
            padding: 0.875rem 1rem; border-radius: 0.625rem;
            background-color: var(--gray-50); border: 1px solid var(--gray-200);
        }
        .dark .cs-visit-card { background-color: var(--gray-900); border-color: var(--gray-800); }
        .cs-visit-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .cs-visit-date { font-size: 0.875rem; font-weight: 600; color: var(--gray-950); }
        .dark .cs-visit-date { color: var(--color-white); }
        .cs-visit-meta { display: flex; align-items: center; gap: 0.625rem; font-size: 0.8125rem; color: var(--gray-500); }
        .cs-visit-doctor { color: var(--gray-600); }
        .dark .cs-visit-doctor { color: var(--gray-400); }
        .cs-status-dot {
            display: inline-flex; align-items: center; gap: 0.3125rem; font-size: 0.75rem; font-weight: 600;
            padding: 0.125rem 0.5rem; border-radius: 9999px;
        }
        .cs-status-dot.has-notes { background-color: var(--success-100); color: var(--success-800); }
        .dark .cs-status-dot.has-notes { background-color: color-mix(in srgb, var(--success-500) 20%, transparent); color: var(--success-300); }
        .cs-status-dot.no-notes { background-color: var(--gray-200); color: var(--gray-600); }
        .dark .cs-status-dot.no-notes { background-color: var(--gray-800); color: var(--gray-400); }
        .cs-status-dot.external { background-color: var(--info-100); color: var(--info-800); }
        .dark .cs-status-dot.external { background-color: color-mix(in srgb, var(--info-500) 20%, transparent); color: var(--info-300); }
        .cs-shared-meds {
            margin-top: 0.5rem; font-size: 0.8125rem; color: var(--gray-700);
            display: flex; flex-direction: column; gap: 0.25rem;
        }
        .dark .cs-shared-meds { color: var(--gray-300); }
        .cs-visit-transcript {
            margin-top: 0.5rem; font-size: 0.8125rem; color: var(--gray-600);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dark .cs-visit-transcript { color: var(--gray-400); }
        .cs-visit-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.625rem; margin-top: 0.625rem; }

        /* Rx pad — full desk from 768px up while the patient is in chamber.
           It started at 1024px, which sent every tablet to the phone modal and
           its far smaller pad. 768px is the same breakpoint the rest of this
           page already turns on at (grid, header actions, hidden thumb strip),
           so the desk now switches with them instead of on a third rule of its
           own; the desk's two columns stack below 1024px in theme.css. */
        .cs-rx-desk-shell { display: none; }
        @media (min-width: 768px) {
            .cs-rx-desk-shell.is-active { display: block; }
            .cs-layout.is-desk-active { display: none !important; }
            .cs-sticky-actions.is-desk-active { display: none !important; }
        }
    </style>

    @if (! $liveSession || ! $booking)
        <x-filament::section>
            <div class="cs-empty">
                <x-filament::icon icon="heroicon-o-user-circle" class="cs-empty-icon" />
                <div class="cs-empty-title">
                    {{ __('Waiting for a patient to be called in') }}
                </div>
                <p class="cs-empty-body">
                    {{ __('When staff or you call the next patient, their record appears here automatically — no search needed.') }}
                </p>
                <p class="cs-empty-hint">
                    {{ __('Seeing patients away from the chamber, or the line is down? Pack a bag on Visiting / camp first.') }}
                    @php
                        try {
                            $visitingUrl = \App\Filament\TenantAdmin\Pages\VisitingDay::getUrl();
                        } catch (\Throwable $e) {
                            $visitingUrl = null;
                        }
                    @endphp
                    @if ($visitingUrl)
                        <a href="{{ $visitingUrl }}" style="font-weight: 600; text-decoration: underline;">{{ __('Visiting / camp') }}</a>
                    @endif
                </p>
                @if (auth()->user()?->canOperateQueueControls())
                    <p class="cs-empty-hint">
                        {{ __('Use the actions above to call the next patient once a live session is running.') }}
                    </p>
                @elseif ($tenant?->isStaffRunQueue())
                    <p class="cs-empty-hint">
                        {{ __('Staff are running the queue today. This screen will update when they call someone in.') }}
                    </p>
                @endif
            </div>
        </x-filament::section>
    @else
        @php
            $showRxDesk = $canWriteNotes && $booking->status === 'in_chamber';
        @endphp

        @if ($showRxDesk)
            {{-- The page header keeps the queue actions at every width from
                 768px up, including here. It used to be hidden at ≥1024px
                 because the desk bar carried its own Complete visit — but that
                 put a queue action inside the prescription pad and left three
                 different breakpoint rules to keep in step. Complete visit now
                 lives only in the page header, so the pairing is simply:
                 below 768px the thumb strip, above it the header. The desk bar
                 is for the prescription and nothing else. --}}

            <div class="cs-rx-desk-shell is-active">
                @include('filament.tenant-admin.components.rx-desk')
            </div>
        @endif

        <div class="cs-layout {{ $showRxDesk ? 'is-desk-active' : '' }}">
            <div class="cs-layout__side">
                {{-- Patient header --}}
                <div class="cs-block--header">
                    <x-filament::section>
                        <div class="cs-header">
                            <div class="cs-identity">
                                <div class="cs-avatar">{{ Str::upper($initials) }}</div>
                                <div>
                                    <div class="cs-name">{{ $displayName }}</div>
                                    @if ($patient?->displayAge() !== null || $patient?->displaySex())
                                        <div class="cs-pills">
                                            @if ($patient?->displayAge() !== null)
                                                <span class="cs-pill">{{ __('Age') }} {{ $patient->displayAge() }}</span>
                                            @endif
                                            @if ($patient?->displaySex())
                                                <span class="cs-pill">{{ ucfirst($patient->displaySex()) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @if ($patient)
                                <div class="cs-summary">
                                    <div class="cs-summary-row">
                                        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="cs-summary-icon" />
                                        <span class="cs-summary-strong">{{ $patient->consultHistoryLabel() }}</span>
                                    </div>
                                    @if ($patient->lastSeenLabel() && $patient->completedVisitCount() > 0)
                                        <div class="cs-summary-row">
                                            <x-filament::icon icon="heroicon-o-calendar-days" class="cs-summary-icon" />
                                            {{ __('Last seen :when', ['when' => $patient->lastSeenLabel()]) }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="cs-summary">
                                    <span class="cs-first-visit">{{ __('First visit — no history') }}</span>
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                </div>

                @if ($patient?->hasClinicalWarnings() || $hasSharedWarnings)
                    <div class="cs-block--warnings">
                        <x-filament::section>
                            <x-slot name="heading">
                                <span style="color: var(--warning-700);">{{ __('Warnings') }}</span>
                            </x-slot>
                            <div class="cs-warning-grid">
                                @if (filled($patient?->allergies))
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Allergies') }}</div>
                                        <div class="cs-warning-value">{{ $patient->allergies }}</div>
                                    </div>
                                @endif
                                @foreach ($sharedClinicalWarnings['allergies'] as $allergy)
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Allergies') }} · {{ __('From another ChamberQ clinic') }}</div>
                                        <div class="cs-warning-value">{{ $allergy }}</div>
                                    </div>
                                @endforeach
                                @if (filled($patient?->conditions))
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Ongoing conditions') }}</div>
                                        <div class="cs-warning-value">{{ $patient->conditions }}</div>
                                    </div>
                                @endif
                                @foreach ($sharedClinicalWarnings['conditions'] as $condition)
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Ongoing conditions') }} · {{ __('From another ChamberQ clinic') }}</div>
                                        <div class="cs-warning-value">{{ $condition }}</div>
                                    </div>
                                @endforeach
                                @if (filled($patient?->medicines))
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Current medicines') }}</div>
                                        <div class="cs-warning-value">{{ $patient->medicines }}</div>
                                    </div>
                                @endif
                                @foreach ($sharedClinicalWarnings['medicines'] as $medicine)
                                    <div class="cs-warning-item">
                                        <div class="cs-warning-label">{{ __('Current medicines') }} · {{ __('From another ChamberQ clinic') }}</div>
                                        <div class="cs-warning-value">{{ $medicine }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    </div>
                @endif

                @if ($canViewNotes)
                    <div class="cs-block--last-visit">
                        <x-filament::section>
                            <x-slot name="heading">
                                <div class="cs-section-heading">
                                    <span>{{ __('Last visit') }}</span>
                                    @if ($lastVisitRecord?->followUpLabel())
                                        <span class="cs-followup-pill">
                                            <x-filament::icon icon="heroicon-o-calendar" style="width: 0.8125rem; height: 0.8125rem;" />
                                            {{ $lastVisitRecord->followUpLabel() }}
                                        </span>
                                    @endif
                                </div>
                            </x-slot>
                            @if ($lastVisitRecord)
                                <div>
                                    @if ($lastVisitRecord->vitalsSummary())
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Vitals') }}</div>
                                            <div class="cs-field-value">{{ $lastVisitRecord->vitalsSummary() }}</div>
                                        </div>
                                    @endif
                                    @if ($lastVisitRecord->diagnosisLabel())
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Diagnosis') }}</div>
                                            <div class="cs-diagnosis">{{ $lastVisitRecord->diagnosisLabel() }}</div>
                                        </div>
                                    @endif
                                    @if (filled($lastVisitRecord->clinical_notes))
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Clinical notes') }}</div>
                                            <div class="cs-field-value">{{ $lastVisitRecord->clinical_notes }}</div>
                                        </div>
                                    @endif
                                    @if (filled($lastVisitRecord->advice))
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Advice') }}</div>
                                            <div class="cs-field-value">{{ $lastVisitRecord->advice }}</div>
                                        </div>
                                    @endif
                                    @if (filled($lastVisitRecord->tests_advised))
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Tests advised') }}</div>
                                            <div class="cs-field-value">{{ $lastVisitRecord->tests_advised }}</div>
                                        </div>
                                    @endif
                                    @if (filled($lastVisitRecord->voice_transcript))
                                        <div class="cs-field">
                                            <div class="cs-field-label">{{ __('Transcript') }}</div>
                                            <div class="cs-field-value">{{ $lastVisitRecord->voice_transcript }}</div>
                                        </div>
                                    @endif

                                    @if (filled($lastVisitRecord->voice_path) || filled($lastVisitRecord->photo_path) || $lastVisitRecord->booking?->booking_date)
                                        <div class="cs-media-row">
                                            @if (filled($lastVisitRecord->voice_path))
                                                <span class="cs-media-chip">
                                                    <x-filament::icon icon="heroicon-o-microphone" style="width: 1rem; height: 1rem;" />
                                                    <audio controls src="{{ tenant_web_route('visit-records.voice', $lastVisitRecord) }}"></audio>
                                                </span>
                                            @endif
                                            @if (filled($lastVisitRecord->photo_path))
                                                <span class="cs-media-chip">
                                                    <x-filament::icon icon="heroicon-o-photo" style="width: 1rem; height: 1rem;" />
                                                    <a href="{{ tenant_web_route('visit-records.photo', $lastVisitRecord) }}" target="_blank">
                                                        {{ __('View prescription photo') }}
                                                    </a>
                                                </span>
                                            @endif
                                            @if ($lastVisitRecord->booking?->booking_date)
                                                <span class="cs-media-chip">
                                                    <x-filament::icon icon="heroicon-o-calendar" style="width: 1rem; height: 1rem;" />
                                                    {{ $lastVisitRecord->booking->booking_date->translatedFormat('j M Y') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @elseif ($patient && $patient->completedVisitCount() > 0)
                                <p class="cs-muted">
                                    {{ __(':count previous visits · no notes recorded', ['count' => $patient->completedVisitCount()]) }}
                                </p>
                            @else
                                <p class="cs-muted">
                                    {{ __('Nothing to show yet.') }}
                                </p>
                            @endif
                        </x-filament::section>
                    </div>

                    <div class="cs-block--past-visits">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('Past visits') }}</x-slot>
                            @if ($visitHistory->isEmpty())
                                <p class="cs-muted">{{ __('No completed visits yet.') }}</p>
                            @else
                                <div class="cs-visits">
                                    @foreach ($visitHistory as $visit)
                                        @php
                                            $visitRecord = $visit->visitRecord;
                                            $hasNotes = $visitRecord?->hasClinicalContent();
                                        @endphp
                                        <div class="cs-visit-card">
                                            <div class="cs-visit-top">
                                                <div class="cs-visit-meta">
                                                    <span class="cs-visit-date">{{ $visit->booking_date?->translatedFormat('j M Y') }}</span>
                                                    @if ($isClinic && $visit->bookable?->doctor?->name)
                                                        <span class="cs-visit-doctor">{{ $visit->bookable->doctor->name }}</span>
                                                    @endif
                                                </div>
                                                @if ($hasNotes && $visitRecord->diagnosisLabel())
                                                    <span class="cs-status-dot has-notes">{{ $visitRecord->diagnosisLabel() }}</span>
                                                @else
                                                    <span class="cs-status-dot no-notes">{{ __('No notes recorded') }}</span>
                                                @endif
                                            </div>

                                            @if (filled($visitRecord?->voice_transcript))
                                                <div class="cs-visit-transcript">{{ $visitRecord->voice_transcript }}</div>
                                            @endif

                                            @if ($visitRecord?->prescription || filled($visitRecord?->voice_path) || filled($visitRecord?->photo_path))
                                                <div class="cs-visit-actions">
                                                    @if ($visitRecord->prescription)
                                                        <x-filament::button
                                                            tag="a"
                                                            href="{{ tenant_web_route('prescriptions.print', $visitRecord->prescription) }}"
                                                            target="_blank"
                                                            size="xs"
                                                            color="gray"
                                                            icon="heroicon-m-printer"
                                                        >
                                                            {{ __('Reprint prescription') }}
                                                        </x-filament::button>
                                                    @endif
                                                    @if (filled($visitRecord->voice_path))
                                                        <span class="cs-media-chip">
                                                            <x-filament::icon icon="heroicon-o-microphone" style="width: 1rem; height: 1rem;" />
                                                            <audio controls src="{{ tenant_web_route('visit-records.voice', $visitRecord) }}"></audio>
                                                        </span>
                                                    @endif
                                                    @if (filled($visitRecord->photo_path))
                                                        <span class="cs-media-chip">
                                                            <x-filament::icon icon="heroicon-o-photo" style="width: 1rem; height: 1rem;" />
                                                            <a href="{{ tenant_web_route('visit-records.photo', $visitRecord) }}" target="_blank">
                                                                {{ __('View prescription photo') }}
                                                            </a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-filament::section>
                    </div>

                    @if ($sharedVisitHistory->isNotEmpty())
                        <div class="cs-block--shared-visits">
                            <x-filament::section>
                                <x-slot name="heading">{{ __('Other ChamberQ clinics') }}</x-slot>
                                <div class="cs-visits">
                                    @foreach ($sharedVisitHistory as $sharedVisit)
                                        @php
                                            $visitRecord = $sharedVisit->visitRecord;
                                            $hasNotes = $sharedVisit->hasNotes();
                                        @endphp
                                        <div class="cs-visit-card">
                                            <div class="cs-visit-top">
                                                <div class="cs-visit-meta">
                                                    <span class="cs-visit-date">{{ $sharedVisit->bookingDate->translatedFormat('j M Y') }}</span>
                                                    <span class="cs-visit-doctor">{{ $sharedVisit->sourceLabel }}</span>
                                                    @if ($sharedVisit->doctorName)
                                                        <span class="cs-visit-doctor">{{ $sharedVisit->doctorName }}</span>
                                                    @endif
                                                </div>
                                                <span class="cs-status-dot external">{{ __('From another ChamberQ clinic') }}</span>
                                            </div>
                                            @if ($hasNotes && $sharedVisit->diagnosisLabel())
                                                <div class="cs-field" style="margin-top:0.5rem;">
                                                    <div class="cs-field-label">{{ __('Diagnosis') }}</div>
                                                    <div class="cs-diagnosis">{{ $sharedVisit->diagnosisLabel() }}</div>
                                                </div>
                                            @endif
                                            @if (filled($visitRecord?->clinical_notes))
                                                <div class="cs-field" style="margin-top:0.5rem;">
                                                    <div class="cs-field-label">{{ __('Clinical notes') }}</div>
                                                    <div class="cs-field-value">{{ $visitRecord->clinical_notes }}</div>
                                                </div>
                                            @endif
                                            @if (filled($visitRecord?->chief_complaint))
                                                <div class="cs-field" style="margin-top:0.5rem;">
                                                    <div class="cs-field-label">{{ __('Chief complaint') }}</div>
                                                    <div class="cs-field-value">{{ $visitRecord->chief_complaint }}</div>
                                                </div>
                                            @endif
                                            @if (filled($visitRecord?->advice))
                                                <div class="cs-field" style="margin-top:0.5rem;">
                                                    <div class="cs-field-label">{{ __('Advice') }}</div>
                                                    <div class="cs-field-value">{{ $visitRecord->advice }}</div>
                                                </div>
                                            @endif
                                            @if ($visitRecord?->vitalsSummary())
                                                <div class="cs-field" style="margin-top:0.5rem;">
                                                    <div class="cs-field-label">{{ __('Vitals') }}</div>
                                                    <div class="cs-field-value">{{ $visitRecord->vitalsSummary() }}</div>
                                                </div>
                                            @endif
                                            @if ($sharedVisit->medicines !== [])
                                                <div class="cs-shared-meds">
                                                    @foreach ($sharedVisit->medicines as $med)
                                                        <div>
                                                            <strong>{{ $med['brand'] }}</strong>
                                                            @if (filled($med['dose'])) — {{ $med['dose'] }} @endif
                                                            @if (filled($med['frequency'])) · {{ $med['frequency'] }} @endif
                                                            @if (filled($med['duration'])) · {{ $med['duration'] }} @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif (! $hasNotes)
                                                <p class="cs-muted" style="margin-top:0.5rem;">{{ __('No notes recorded') }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </x-filament::section>
                        </div>
                    @endif
                @endif
            </div>

            <div class="cs-layout__main">
                @php
                    $written = $this->currentVisitRecord;
                    $hasPrescription = (bool) $written?->prescription?->items->isNotEmpty();
                @endphp

                @if ($canWriteNotes && $booking->status === 'in_chamber')
                    <div class="cs-block--write">
                        <x-filament::section>
                            <div class="cs-write-row">
                                <div class="cs-write-summary">
                                    @if ($written)
                                        <div class="cs-write-title">
                                            {{ $hasPrescription ? __('Prescription so far') : __('Notes so far — no medicines yet') }}
                                        </div>
                                        <div class="cs-write-detail">
                                            @if ($written->vitalsSummary())
                                                <span class="cs-write-chip">{{ $written->vitalsSummary() }}</span>
                                            @endif
                                            @if ($written->diagnosisLabel())
                                                <span class="cs-write-chip">{{ $written->diagnosisLabel() }}</span>
                                            @endif
                                            @if ($hasPrescription)
                                                <span class="cs-write-chip">
                                                    {{ trans_choice(':count medicine|:count medicines', $written->prescription->items->count(), ['count' => $written->prescription->items->count()]) }}
                                                </span>
                                            @endif
                                            @if (filled($written->clinical_notes))
                                                <span class="cs-write-chip">{{ __('Clinical notes') }}</span>
                                            @endif
                                            @if (filled($written->advice))
                                                <span class="cs-write-chip">{{ __('Advice') }}</span>
                                            @endif
                                            @if (filled($written->tests_advised))
                                                <span class="cs-write-chip">{{ __('Tests advised') }}</span>
                                            @endif
                                            @if (filled($written->reports_seen))
                                                <span class="cs-write-chip">{{ __('Reports seen') }}</span>
                                            @endif
                                            @if ($written->followUpLabel())
                                                <span class="cs-write-chip">{{ __('Follow-up set') }}</span>
                                            @endif
                                            @if (filled($written->voice_path))
                                                <span class="cs-write-chip">{{ __('Voice note') }}</span>
                                            @endif
                                            @if (filled($written->photo_path))
                                                <span class="cs-write-chip">{{ __('Photo') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="cs-write-title">{{ __('Nothing written yet') }}</div>
                                        <div class="cs-write-hint">
                                            {{ __('Write while the patient is with you — saving does not end the visit.') }}
                                        </div>
                                    @endif
                                </div>
                                <x-filament::button
                                    class="cs-primary-btn"
                                    color="primary"
                                    icon="heroicon-m-pencil-square"
                                    wire:click="mountAction('writePrescription')"
                                >
                                    {{ $hasPrescription ? __('Edit prescription') : __('Write prescription') }}
                                </x-filament::button>
                            </div>
                        </x-filament::section>
                    </div>
                @endif

                @if ($booking->status === 'completed')
                    <div class="cs-block--done">
                        <x-filament::section>
                            <div class="cs-done-row">
                                @include('filament.tenant-admin.components.call-next-nudge', ['booking' => $booking])
                                @include('filament.tenant-admin.components.prescription-share-actions', [
                                    'booking' => $booking,
                                    'prescription' => $booking->visitRecord?->prescription,
                                ])
                            </div>
                        </x-filament::section>
                    </div>
                @endif

                @if ($isClinic)
                    <div class="cs-block--lab">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('Lab orders') }}</x-slot>
                            <p class="cs-muted">
                                {{ __('Lab order history — coming in a later update.') }}
                            </p>
                        </x-filament::section>
                    </div>
                @endif
            </div>
        </div>

        @if (auth()->user()?->canOperateQueueControls())
            <div class="cs-sticky-actions cq-freeze-queue {{ $showRxDesk ? 'is-desk-active' : '' }}">
                @if ($booking->status === 'called')
                    <x-filament::button class="cs-sticky-actions__btn" color="success" wire:click="mountAction('patientArrived')">
                        {{ __('Patient arrived') }}
                    </x-filament::button>
                @elseif ($canWriteNotes && $booking->status === 'in_chamber')
                    <x-filament::button class="cs-sticky-actions__btn cs-complete-visit-btn" color="success" wire:click="mountAction('completeVisit')">
                        {{ __('Complete visit') }}
                    </x-filament::button>
                @elseif ($booking->status === 'completed')
                    <x-filament::button class="cs-sticky-actions__btn" color="primary" wire:click="mountAction('callNext')">
                        {{ __('Call next patient') }}
                    </x-filament::button>
                @endif
            </div>
        @endif
    @endif
</x-filament-panels::page>
