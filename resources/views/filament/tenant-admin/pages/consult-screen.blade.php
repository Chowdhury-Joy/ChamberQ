<x-filament-panels::page wire:poll.3s>
    @php
        $booking = $this->currentBooking;
        $patient = $this->currentPatient;
        $liveSession = $this->runningLiveSession;
        $tenant = tenant();
        $isClinic = $tenant?->isClinic() ?? false;
        $visitHistory = $this->visitHistory;
        $lastVisitRecord = $this->lastVisitRecord;
        $canViewNotes = auth()->user()?->canViewVisitNotes() ?? false;
        $catchUpCount = $this->catchUpCount;
    @endphp

    @if ($canViewNotes && $catchUpCount > 0 && $liveSession)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    {{ __(':count patients today without notes', ['count' => $catchUpCount]) }}
                </span>
                <x-filament::button
                    size="sm"
                    color="warning"
                    wire:click="mountAction('catchUpNotes')"
                >
                    {{ __('Fill in now') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @if (! $liveSession || ! $booking)
        <x-filament::section>
            <div class="py-12 text-center">
                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    {{ __('Waiting for a patient to be called in') }}
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-lg mx-auto">
                    {{ __('When staff or you call the next patient, their record appears here automatically — no search needed.') }}
                </p>
                @if (auth()->user()?->canOperateQueueControls())
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-4 max-w-lg mx-auto">
                        {{ __('Use the actions above to call the next patient once a live session is running.') }}
                    </p>
                @elseif ($tenant?->isStaffRunQueue())
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-4 max-w-lg mx-auto">
                        {{ __('Staff are running the queue today. This screen will update when they call someone in.') }}
                    </p>
                @endif
            </div>
        </x-filament::section>
    @else
        <div class="space-y-6">
            {{-- Patient header --}}
            <x-filament::section>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $patient?->name ?? $booking->patient_name }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                            @if ($patient?->displayAge() !== null)
                                <span>{{ __('Age') }} {{ $patient->displayAge() }}</span>
                            @endif
                            @if ($patient?->displaySex())
                                <span>{{ ucfirst($patient->displaySex()) }}</span>
                            @endif
                        </div>
                    </div>
                    @if ($patient)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                            <div class="font-medium">{{ $patient->consultHistoryLabel() }}</div>
                            @if ($patient->lastSeenLabel() && $patient->completedVisitCount() > 0)
                                <div class="text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Last seen :when', ['when' => $patient->lastSeenLabel()]) }}
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                            {{ __('First visit — no history') }}
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Warnings --}}
            @if ($patient?->hasClinicalWarnings())
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="text-amber-700 dark:text-amber-400">{{ __('Warnings') }}</span>
                    </x-slot>
                    <div class="space-y-3 rounded-lg border-2 border-amber-300 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/40 p-4">
                        @if (filled($patient->allergies))
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">{{ __('Allergies') }}</div>
                                <div class="text-sm text-amber-900 dark:text-amber-100">{{ $patient->allergies }}</div>
                            </div>
                        @endif
                        @if (filled($patient->conditions))
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">{{ __('Ongoing conditions') }}</div>
                                <div class="text-sm text-amber-900 dark:text-amber-100">{{ $patient->conditions }}</div>
                            </div>
                        @endif
                        @if (filled($patient->medicines))
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">{{ __('Current medicines') }}</div>
                                <div class="text-sm text-amber-900 dark:text-amber-100">{{ $patient->medicines }}</div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endif

            {{-- Last visit notes --}}
            @if ($canViewNotes)
                <x-filament::section>
                    <x-slot name="heading">{{ __('Last visit') }}</x-slot>
                    @if ($lastVisitRecord)
                        <div class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
                            @if ($lastVisitRecord->diagnosisLabel())
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Diagnosis') }}</div>
                                    <div class="font-medium">{{ $lastVisitRecord->diagnosisLabel() }}</div>
                                </div>
                            @endif
                            @if (filled($lastVisitRecord->advice))
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Advice') }}</div>
                                    <div class="whitespace-pre-wrap">{{ $lastVisitRecord->advice }}</div>
                                </div>
                            @endif
                            @if (filled($lastVisitRecord->tests_advised))
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Tests advised') }}</div>
                                    <div class="whitespace-pre-wrap">{{ $lastVisitRecord->tests_advised }}</div>
                                </div>
                            @endif
                            @if (filled($lastVisitRecord->voice_transcript))
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Transcript') }}</div>
                                    <div class="whitespace-pre-wrap">{{ $lastVisitRecord->voice_transcript }}</div>
                                </div>
                            @endif
                            @if (filled($lastVisitRecord->voice_path))
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Voice note') }}</div>
                                    <audio controls class="w-full max-w-md" src="{{ tenant_web_route('visit-records.voice', $lastVisitRecord) }}"></audio>
                                </div>
                            @endif
                            @if (filled($lastVisitRecord->photo_path))
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Prescription photo') }}</div>
                                    <a href="{{ tenant_web_route('visit-records.photo', $lastVisitRecord) }}" target="_blank" class="text-primary-600 hover:underline">
                                        {{ __('View photo') }}
                                    </a>
                                </div>
                            @endif
                            @if ($lastVisitRecord->booking?->booking_date)
                                <div class="text-xs text-gray-500 dark:text-gray-400 pt-1">
                                    {{ $lastVisitRecord->booking->booking_date->translatedFormat('j M Y') }}
                                </div>
                            @endif
                        </div>
                    @elseif ($patient && $patient->completedVisitCount() > 0)
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ __(':count previous visits · no notes recorded', ['count' => $patient->completedVisitCount()]) }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Nothing to show yet.') }}
                        </p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">{{ __('Follow-up') }}</x-slot>
                    @if ($lastVisitRecord?->follow_up_date)
                        <p class="text-sm text-gray-700 dark:text-gray-200">
                            {{ __('Asked to return :date', ['date' => $lastVisitRecord->follow_up_date->translatedFormat('j M Y')]) }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No follow-up recorded.') }}
                        </p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">{{ __('Past visits') }}</x-slot>
                    @if ($visitHistory->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No completed visits yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($visitHistory as $visit)
                                @php
                                    $visitRecord = $visit->visitRecord;
                                    $hasNotes = $visitRecord?->hasClinicalContent();
                                @endphp
                                <li class="py-3 flex flex-col gap-2 text-sm">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $visit->booking_date?->translatedFormat('j M Y') }}
                                        </span>
                                        @if ($isClinic)
                                            <span class="text-gray-500 dark:text-gray-400">
                                                @if ($visit->bookable?->doctor?->name)
                                                    {{ $visit->bookable->doctor->name }}
                                                @endif
                                            </span>
                                        @endif
                                        <span class="text-gray-500 dark:text-gray-400">
                                            @if ($hasNotes && $visitRecord->diagnosisLabel())
                                                {{ $visitRecord->diagnosisLabel() }}
                                            @else
                                                {{ __('No notes recorded') }}
                                            @endif
                                        </span>
                                    </div>
                                    @if ($visitRecord?->prescription)
                                        <div>
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
                                        </div>
                                    @endif
                                    @if (filled($visitRecord?->voice_path))
                                        <div>
                                            <audio controls class="w-full max-w-sm" src="{{ tenant_web_route('visit-records.voice', $visitRecord) }}"></audio>
                                        </div>
                                    @endif
                                    @if (filled($visitRecord?->voice_transcript))
                                        <div class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
                                            {{ $visitRecord->voice_transcript }}
                                        </div>
                                    @endif
                                    @if (filled($visitRecord?->photo_path))
                                        <div>
                                            <a
                                                href="{{ tenant_web_route('visit-records.photo', $visitRecord) }}"
                                                target="_blank"
                                                class="text-primary-600 hover:underline text-xs"
                                            >
                                                {{ __('View prescription photo') }}
                                            </a>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            @endif

            @if ($isClinic)
                <x-filament::section>
                    <x-slot name="heading">{{ __('Lab orders') }}</x-slot>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Lab order history — coming in a later update.') }}
                    </p>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
