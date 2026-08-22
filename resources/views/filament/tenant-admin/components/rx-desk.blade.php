{{--
    Desktop Rx pad (≥1024px, Consult Screen, patient in chamber).
    Alpine owns the medicine table so chip taps are not Livewire round trips;
    wire:click Save posts the whole payload once.
--}}
@php
    use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
    use App\Support\ComplaintChips;
    use App\Support\FindingChips;
    use App\Support\IndicationSuggestions;
    use App\Support\InvestigationChips;
    use App\Support\PrescriptionTiming;

    $canAttachVisitPaper = auth()->user()?->attachesVisitPaperOnConsult() ?? false;
    $written = $this->currentVisitRecord;
    $lastVisitRecord = $this->lastVisitRecord;
    $patient = $this->currentPatient;
    $booking = $this->currentBooking;
    $displayName = $patient?->name ?? $booking?->patient_name;
    $state = VisitNotesFormSchema::stateFromRecord($written);
    $lastState = VisitNotesFormSchema::stateFromRecord($lastVisitRecord);
    $deskItems = VisitNotesFormSchema::deskItemsFromRecord($written);
    $lastItems = VisitNotesFormSchema::deskItemsFromRecord($lastVisitRecord);
    $reportPhotoPaths = array_values(array_filter($written?->report_photo_paths ?? []));
    $reportPhotoPreviews = [];
    if ($written) {
        foreach ($reportPhotoPaths as $index => $path) {
            $reportPhotoPreviews[] = [
                'path' => $path,
                'preview' => tenant_web_url('/visit-records/'.$written->id.'/report-photos/'.$index),
            ];
        }
    }
    $frequencyPresets = VisitNotesFormSchema::FREQUENCY_PRESETS;
    $durationPresets = VisitNotesFormSchema::DURATION_PRESETS;
    $timingOptions = collect(PrescriptionTiming::labels())
        ->map(fn (string $label, string $key) => ['key' => $key, 'label' => __($label)])
        ->values()
        ->all();
    // Advice and History are the doctor's own vocabulary, edited on My
    // medicines. The service hands back the shipped chips with this doctor's
    // edits, removals and additions already applied.
    $doctorChips = app(\App\Services\DoctorChipService::class);
    $historyChipRows = $doctorChips->forDoctor(auth()->user(), \App\Models\DoctorChip::KIND_HISTORY);
    $historyChips = array_values(array_unique(array_map(fn (array $chip) => $chip['label'], $historyChipRows)));
    $historyPrimary = array_values(array_unique(
        array_map(fn (array $chip) => $chip['label'], array_filter($historyChipRows, fn (array $chip) => $chip['is_primary']))
    ));
    $complaintGroups = ComplaintChips::groups();
    $complaintDurations = ComplaintChips::durations();
    $investigationOptions = InvestigationChips::all();
    $adviceChips = $doctorChips->forDoctor(auth()->user(), \App\Models\DoctorChip::KIND_ADVICE);
    $findingChips = FindingChips::all();
    $indicationCommon = IndicationSuggestions::common();

    // H/O the chamber already knows: the patient's stored ongoing conditions
    // and current medicines. These were captured once at registration and then
    // re-typed at every visit; seeding the box is the whole point.
    $historySeed = VisitNotesFormSchema::historySeedFromPatient($patient);

    // Path tenants live at /{slug}/… on 127.0.0.1 / localhost. A bare
    // `/api/medicines/search` hits the central app and 404s — which is exactly
    // why the suggestion list never appeared when developing locally. Custom
    // domains keep the root path. Same helper the voice recorder already uses.
    $medicineSearchUrl = tenant_web_url('/api/medicines/search');
    $medicineDosesUrl = tenant_web_url('/api/medicines/doses');
    $conditionSearchUrl = tenant_web_url('/api/conditions/search');
    $diagnosisAdvice = (string) ($written?->condition?->adviceForLocale() ?? '');
    $diagnosisTests = (string) ($written?->condition?->default_tests ?? '');
@endphp

{{--
    The key is the booking and nothing else, and the subtree is `wire:ignore`.

    The key used to carry the visit record's `updated_at`, so any write to that
    row — the desk's own save, a staff paper entry, a follow-up stamp — changed
    the key on the next 3s poll and remounted the component, throwing away
    whatever the doctor had typed and not yet saved.

    Dropping the timestamp on its own is not enough, and briefly made things
    worse. That timestamp was also what made the post-save remount *clean*: a
    changed key replaces the element outright, so Alpine re-initialises the
    whole subtree consistently. With a stable key, Livewire instead **morphs**
    the pad — and because `x-data="rxDesk({...})"` is rendered from the record,
    its attribute string changes after every save, which re-runs the component's
    init against nodes whose effects have already been torn down. Every `x-show`
    on the pad then stops responding: the complaint picker, the brand
    suggestions, the timing chips. Caught in the browser, not by the tests,
    which assert markup and never execute Alpine.

    `wire:ignore` settles it. Alpine already owns this subtree outright (see the
    header comment), so there is nothing for the morph to contribute between
    patients — and when the patient *does* change, the key changes with them and
    Livewire replaces the element wholesale, which is the one moment a fresh
    mount is wanted. The saves that used to drive those re-renders are
    `#[Renderless]` for the same reason.
--}}
<div
    class="cs-rx-desk"
    wire:key="rx-desk-{{ $booking?->id }}"
    wire:ignore
    x-effect="queueDraftSave()"
    x-on:pointerdown.window="flushIfClickedAway($event)"
    x-on:visibilitychange.document="flush()"
    x-on:beforeunload.window="if (isDirty()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-data="rxDesk({
        items: {{ \Illuminate\Support\Js::from($deskItems) }},
        lastItems: {{ \Illuminate\Support\Js::from($lastItems) }},
        complaints: {{ \Illuminate\Support\Js::from(ComplaintChips::parse($state['chief_complaint'] ?? null)) }},
        complaintDurations: {{ \Illuminate\Support\Js::from(
            collect($complaintDurations)->map(fn (string $d) => [
                'value' => $d,
                'label' => __($d),
                'short' => match ($d) {
                    '3 days' => '3d',
                    '1 week' => '1wk',
                    '15 days' => '15d',
                    '1 month' => '1mo',
                    '6 months' => '6mo',
                    default => $d,
                },
            ])->values()->all()
        ) }},
        history: {{ \Illuminate\Support\Js::from($state['history'] ?? '') }},
        historySeed: {{ \Illuminate\Support\Js::from($historySeed) }},
        historyChips: {{ \Illuminate\Support\Js::from($historyChips) }},
        historyPrimary: {{ \Illuminate\Support\Js::from($historyPrimary) }},
        investigations: {{ \Illuminate\Support\Js::from(InvestigationChips::parse($state['tests_advised'] ?? null)) }},
        investigationOptions: {{ \Illuminate\Support\Js::from($investigationOptions) }},
        adviceChips: {{ \Illuminate\Support\Js::from($adviceChips) }},
        findingChips: {{ \Illuminate\Support\Js::from($findingChips) }},
        indicationCommon: {{ \Illuminate\Support\Js::from($indicationCommon) }},
        lastDiagnosis: {{ \Illuminate\Support\Js::from($lastState['diagnosis'] ?? '') }},
        lastDiagnosisLabel: {{ \Illuminate\Support\Js::from($lastVisitRecord?->diagnosisLabel() ?? '') }},
        lastTestsAdvised: {{ \Illuminate\Support\Js::from($lastVisitRecord?->tests_advised ?? '') }},
        lastAdvice: {{ \Illuminate\Support\Js::from($lastVisitRecord?->advice ?? '') }},
        onExamination: {{ \Illuminate\Support\Js::from($state['on_examination'] ?? '') }},
        diagnosis: {{ \Illuminate\Support\Js::from($state['diagnosis'] ?? '') }},
        diagnosisLabel: {{ \Illuminate\Support\Js::from($written?->diagnosisLabel() ?? '') }},
        weightKg: {{ \Illuminate\Support\Js::from($state['weight_kg'] ?? '') }},
        bpSystolic: {{ \Illuminate\Support\Js::from($state['bp_systolic'] ?? '') }},
        bpDiastolic: {{ \Illuminate\Support\Js::from($state['bp_diastolic'] ?? '') }},
        pulseBpm: {{ \Illuminate\Support\Js::from($state['pulse_bpm'] ?? '') }},
        spo2Percent: {{ \Illuminate\Support\Js::from($state['spo2_percent'] ?? '') }},
        temperatureF: {{ \Illuminate\Support\Js::from($state['temperature_f'] ?? '') }},
        lastWeightKg: {{ \Illuminate\Support\Js::from($lastVisitRecord?->weight_kg) }},
        lastBpSystolic: {{ \Illuminate\Support\Js::from($lastVisitRecord?->bp_systolic) }},
        lastBpDiastolic: {{ \Illuminate\Support\Js::from($lastVisitRecord?->bp_diastolic) }},
        lastPulseBpm: {{ \Illuminate\Support\Js::from($lastVisitRecord?->pulse_bpm) }},
        lastSpo2Percent: {{ \Illuminate\Support\Js::from($lastVisitRecord?->spo2_percent) }},
        lastTemperatureF: {{ \Illuminate\Support\Js::from($lastVisitRecord?->temperature_f) }},
        advice: {{ \Illuminate\Support\Js::from($state['advice'] ?? '') }},
        reportsSeen: {{ \Illuminate\Support\Js::from($state['reports_seen'] ?? '') }},
        reportPhotos: {{ \Illuminate\Support\Js::from($reportPhotoPaths) }},
        reportPhotoPreviews: {{ \Illuminate\Support\Js::from($reportPhotoPreviews) }},
        reportPhotoUploadUrl: {{ \Illuminate\Support\Js::from(tenant_web_url('/api/visit-media/upload-report-photo')) }},
        canAttachVisitPaper: {{ \Illuminate\Support\Js::from($canAttachVisitPaper) }},
        followUpRelative: {{ \Illuminate\Support\Js::from($state['follow_up_relative'] ?? '') }},
        followUpDate: {{ \Illuminate\Support\Js::from($state['follow_up_date'] ?? '') }},
        followUpNote: {{ \Illuminate\Support\Js::from($state['follow_up_note'] ?? '') }},
        voicePath: {{ \Illuminate\Support\Js::from($state['voice_path'] ?? '') }},
        voiceTranscript: {{ \Illuminate\Support\Js::from($state['voice_transcript'] ?? '') }},
        clinicalNotes: {{ \Illuminate\Support\Js::from($state['clinical_notes'] ?? '') }},
        frequencyPresets: {{ \Illuminate\Support\Js::from($frequencyPresets) }},
        durationPresets: {{ \Illuminate\Support\Js::from($durationPresets) }},
        timingOptions: {{ \Illuminate\Support\Js::from($timingOptions) }},
        medicineSearchUrl: {{ \Illuminate\Support\Js::from($medicineSearchUrl) }},
        medicineDosesUrl: {{ \Illuminate\Support\Js::from($medicineDosesUrl) }},
        conditionSearchUrl: {{ \Illuminate\Support\Js::from($conditionSearchUrl) }},
        diagnosisAdvice: {{ \Illuminate\Support\Js::from($diagnosisAdvice) }},
        diagnosisTests: {{ \Illuminate\Support\Js::from($diagnosisTests) }},
        freeDiagnosisPrefix: {{ \Illuminate\Support\Js::from(VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX) }},
        packs: {{ \Illuminate\Support\Js::from($this->rxPacks) }},
        myMedicines: {{ \Illuminate\Support\Js::from($this->myMedicines) }},
        patientAllergies: {{ \Illuminate\Support\Js::from($patient?->allergies) }},
        bookingId: {{ \Illuminate\Support\Js::from($booking?->id) }},
        patientName: {{ \Illuminate\Support\Js::from($displayName) }},
        patientAge: {{ \Illuminate\Support\Js::from($patient?->displayAge()) }},
    })"
>
    <div class="cs-rx-desk__bar">
        <div class="cs-rx-desk__identity">
            <div class="cs-rx-desk__name-line">
                <span class="cs-rx-desk__name">{{ $displayName }}</span>
                @if ($patient?->displayAge() !== null)
                    <span class="cs-rx-desk__meta-inline">· {{ $patient->displayAge() }}</span>
                @endif
                @if ($patient?->displaySex())
                    <span class="cs-rx-desk__meta-inline">· {{ ucfirst($patient->displaySex()) }}</span>
                @endif
                @if ($booking?->serial_number)
                    <span class="cs-rx-desk__meta-inline">· {{ __('Serial') }} {{ $booking->serial_number }}</span>
                @endif
            </div>
            @if ($patient?->hasClinicalWarnings() && filled($patient->allergies))
                <div class="cs-rx-desk__allergy">{{ __('Allergies') }}: {{ $patient->allergies }}</div>
            @elseif ($patient)
                <div class="cs-rx-desk__meta">{{ $patient->consultHistoryLabel() }}</div>
            @endif
        </div>
        {{--
            Two actions, one of them filled. The bar used to carry four —
            Preview, Save & print, Save only, Complete visit — with two
            saturated buttons side by side and nothing saying which one ends
            the consult.

            **Save only** is gone: "Save & print" already saves, and a second
            save button asked the doctor to decide between two things that
            differ only in what happens afterwards.

            **Complete visit** moved back to the Consult Screen's own header.
            It closes the visit and moves the queue on — that is a page action,
            not something the prescription pad does — and keeping it here meant
            hiding the page header at ≥1024px, which broke the complementary
            breakpoint pairing recorded in `bug_history.md`.
        --}}
        <div class="cs-rx-desk__bar-actions">
            {{-- The pad saves itself, so the doctor never has to wonder — but
                 "it saves automatically" is only trustworthy if the screen says
                 so out loud. Three states, one line, no button. --}}
            <span
                class="cs-rx-desk__save-state"
                :class="{
                    'is-unsaved': saveState === 'unsaved',
                    'is-saving': saveState === 'saving',
                    'is-saved': saveState === 'saved',
                }"
                x-show="saveState !== 'clean'"
                x-cloak
            >
                <span x-show="saveState === 'unsaved'">{{ __('Unsaved') }}</span>
                <span x-show="saveState === 'saving'" x-cloak>{{ __('Saving…') }}</span>
                <span x-show="saveState === 'saved'" x-cloak>{{ __('Saved') }}</span>
            </span>
            <x-filament::button type="button" color="gray" size="sm" x-on:click="save({ preview: true })">
                {{ __('Preview') }}
            </x-filament::button>
            <label
                class="cs-rx-desk__my-paper"
                title="{{ __('Skip letterhead. Tick this if the paper already has your name printed.') }}"
            >
                <input
                    type="checkbox"
                    x-model="onMyPaper"
                    x-on:change="rememberOnMyPaper()"
                >
                {{ __('My paper') }}
            </label>
            <x-filament::button type="button" color="primary" size="sm" icon="heroicon-o-printer" x-on:click="save({ print: true })">
                {{ __('Save & print') }}
            </x-filament::button>
        </div>
    </div>

    <div class="cs-rx-desk__grid">
        <div class="cs-rx-desk__left">
            <section class="cs-rx-desk__card">
                <h3>{{ __('Chief complaints') }}</h3>
                <table class="cs-rx-desk__mini-table" x-show="complaints.length" x-cloak>
                    <tbody>
                        <template x-for="(row, index) in complaints" :key="index">
                            <tr>
                                <td class="cs-rx-desk__mini-name" x-text="row.complaint"></td>
                                <td>
                                    <select class="cs-rx-desk__mini-select" x-model="row.duration">
                                        <option value="">—</option>
                                        <template x-for="duration in complaintDurations" :key="duration.value">
                                            <option :value="duration.value" x-text="duration.short" :selected="row.duration === duration.value"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="cs-rx-desk__mini-actions">
                                    <button type="button" class="cs-rx-desk__remove" x-on:click="removeComplaint(index)" title="{{ __('Remove') }}">×</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <button type="button" class="cs-rx-desk__add" x-on:click="showAddCc = !showAddCc">
                    <span x-text="showAddCc ? '{{ __('Close') }}' : '{{ __('+ Add') }}'"></span>
                </button>
                <div class="cs-rx-desk__picker" x-show="showAddCc" x-cloak>
                    <div class="cs-rx-desk__chips">
                        @foreach ($complaintGroups['General'] as $chip)
                            <button
                                type="button"
                                class="cs-rx-desk__chip"
                                :class="{ 'is-on': hasComplaint({{ \Illuminate\Support\Js::from($chip) }}) }"
                                x-on:click="appendComplaint({{ \Illuminate\Support\Js::from($chip) }})"
                            >{{ __($chip) }}</button>
                        @endforeach
                        <button type="button" class="cs-rx-desk__chip cs-rx-desk__chip--more" x-on:click="showAllComplaints = !showAllComplaints">
                            <span x-show="!showAllComplaints">{{ __('More…') }}</span>
                            <span x-show="showAllComplaints" x-cloak>{{ __('Less') }}</span>
                        </button>
                    </div>
                    <div class="cs-rx-desk__chip-groups" x-show="showAllComplaints" x-cloak>
                        @foreach ($complaintGroups as $group => $chips)
                            @continue($group === 'General')
                            <div class="cs-rx-desk__chip-group">
                                <span class="cs-rx-desk__chip-group-label">{{ __($group) }}</span>
                                @foreach ($chips as $chip)
                                    <button
                                        type="button"
                                        class="cs-rx-desk__chip"
                                        :class="{ 'is-on': hasComplaint({{ \Illuminate\Support\Js::from($chip) }}) }"
                                        x-on:click="appendComplaint({{ \Illuminate\Support\Js::from($chip) }})"
                                    >{{ __($chip) }}</button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="cs-rx-desk__cc-custom">
                        <input
                            type="text"
                            x-model="customComplaint"
                            x-on:keydown.enter.prevent="addCustomComplaint()"
                            placeholder="{{ __('Or type a complaint and press Enter') }}"
                        >
                    </div>
                </div>
            </section>

            <section class="cs-rx-desk__card">
                <h3>{{ __('History') }}</h3>
                <div class="cs-rx-desk__chips cs-rx-desk__chips--toggles">
                    @foreach ($historyPrimary as $chip)
                        <button
                            type="button"
                            class="cs-rx-desk__chip"
                            :class="{ 'is-on': historySelected[{{ \Illuminate\Support\Js::from($chip) }}] }"
                            x-on:click="toggleHistory({{ \Illuminate\Support\Js::from($chip) }})"
                        >
                            <span x-show="historySelected[{{ \Illuminate\Support\Js::from($chip) }}]" x-cloak>✓</span>
                            {{ $chip }}
                        </button>
                    @endforeach
                    <button type="button" class="cs-rx-desk__chip cs-rx-desk__chip--more" x-on:click="showMoreHistory = !showMoreHistory">
                        <span x-show="!showMoreHistory">{{ __('More…') }}</span>
                        <span x-show="showMoreHistory" x-cloak>{{ __('Less') }}</span>
                    </button>
                </div>
                <div class="cs-rx-desk__chips" x-show="showMoreHistory" x-cloak>
                    @foreach ($historyChips as $chip)
                        @continue(in_array($chip, $historyPrimary, true))
                        <button
                            type="button"
                            class="cs-rx-desk__chip"
                            :class="{ 'is-on': historySelected[{{ \Illuminate\Support\Js::from($chip) }}] }"
                            x-on:click="toggleHistory({{ \Illuminate\Support\Js::from($chip) }})"
                        >{{ $chip }}</button>
                    @endforeach
                </div>
                <textarea rows="1" x-model="historyNote" placeholder="{{ __('Other history / current medicines') }}"></textarea>
                <p class="cs-rx-desk__hint" x-show="historyFromRecord" x-cloak>
                    {{ __('From the patient record — edit or clear it.') }}
                </p>
            </section>

            <section class="cs-rx-desk__card">
                <h3>{{ __('On examination') }}</h3>
                {{--
                    Vitals are never pre-filled. Last visit's reading is grey
                    reference only — a number carried forward would put a
                    measurement the doctor never took onto a document he signs.
                --}}
                <div class="cs-rx-desk__oe-table">
                    <label class="cs-rx-desk__vital">
                        <span class="cs-rx-desk__vital-label">{{ __('Wt') }}</span>
                        <input type="number" step="0.1" min="0.5" max="300" x-model="weightKg" placeholder="kg" aria-label="{{ __('Weight') }}">
                        <span class="cs-rx-desk__unit">kg</span>
                        <span class="cs-rx-desk__last-vital" x-show="lastWeightKg !== null && lastWeightKg !== ''" x-text="lastWeightKg" x-cloak></span>
                    </label>
                    <div class="cs-rx-desk__vital">
                        <span class="cs-rx-desk__vital-label">{{ __('BP') }}</span>
                        <input type="number" min="60" max="250" x-model="bpSystolic" placeholder="sys" class="cs-rx-desk__bp" aria-label="{{ __('Systolic') }}">
                        <span class="cs-rx-desk__bp-sep">/</span>
                        <input type="number" min="30" max="150" x-model="bpDiastolic" placeholder="dia" class="cs-rx-desk__bp" aria-label="{{ __('Diastolic') }}">
                        <span class="cs-rx-desk__last-vital" x-show="lastBpSystolic && lastBpDiastolic" x-text="lastBpSystolic + '/' + lastBpDiastolic" x-cloak></span>
                    </div>
                    <label class="cs-rx-desk__vital">
                        <span class="cs-rx-desk__vital-label" title="{{ __('Pulse') }}">P</span>
                        <input type="number" min="30" max="250" x-model="pulseBpm" placeholder="78" aria-label="{{ __('Pulse') }}">
                        <span class="cs-rx-desk__last-vital" x-show="lastPulseBpm !== null && lastPulseBpm !== ''" x-text="lastPulseBpm" x-cloak></span>
                    </label>
                    <label class="cs-rx-desk__vital">
                        <span class="cs-rx-desk__vital-label">{{ __('SpO₂') }}</span>
                        <input type="number" min="50" max="100" x-model="spo2Percent" placeholder="98" aria-label="{{ __('SpO₂') }}">
                        <span class="cs-rx-desk__unit">%</span>
                        <span class="cs-rx-desk__last-vital" x-show="lastSpo2Percent !== null && lastSpo2Percent !== ''" x-text="lastSpo2Percent" x-cloak></span>
                    </label>
                    <label class="cs-rx-desk__vital">
                        <span class="cs-rx-desk__vital-label" title="{{ __('Temp') }}">T</span>
                        <input type="number" step="0.1" min="90" max="110" x-model="temperatureF" placeholder="100.5" aria-label="{{ __('Temperature') }}">
                        <span class="cs-rx-desk__unit">°F</span>
                        <span class="cs-rx-desk__last-vital" x-show="lastTemperatureF !== null && lastTemperatureF !== ''" x-text="lastTemperatureF" x-cloak></span>
                    </label>
                </div>
                <div class="cs-rx-desk__chips">
                    @foreach ($findingChips as $chip)
                        <button
                            type="button"
                            class="cs-rx-desk__chip"
                            :class="{ 'is-on': hasFinding({{ \Illuminate\Support\Js::from($chip) }}) }"
                            x-on:click="toggleFinding({{ \Illuminate\Support\Js::from($chip) }})"
                        >{{ __($chip) }}</button>
                    @endforeach
                </div>
                <textarea rows="1" class="cs-rx-desk__oe-notes" x-model="onExamination" placeholder="{{ __('Other findings') }}"></textarea>
                @include('filament.tenant-admin.components.vitals-trend-charts', ['trend' => $this->vitalsTrend])
            </section>

            <section class="cs-rx-desk__card">
                <h3>{{ __('Diagnosis') }}</h3>
                <div class="cs-rx-desk__dx-row">
                    <template x-if="diagnosisLabel">
                        <span class="cs-rx-desk__pill">
                            <span x-text="diagnosisLabel"></span>
                            <button type="button" x-on:click="clearDiagnosis()" title="{{ __('Remove') }}">×</button>
                        </span>
                    </template>
                    <input
                        type="search"
                        x-model="diagnosisQuery"
                        x-on:input.debounce.300ms="searchDiagnosis()"
                        placeholder="{{ __('Search diagnosis…') }}"
                    >
                </div>
                <ul class="cs-rx-desk__suggest" x-show="diagnosisResults.length" x-cloak>
                    <template x-for="row in diagnosisResults" :key="row.id">
                        <li>
                            <button type="button" x-on:click="pickDiagnosis(row)" x-text="row.name"></button>
                        </li>
                    </template>
                </ul>
                <div class="cs-rx-desk__chips" x-show="suggestedPacks.length || diagnosisTests" x-cloak>
                    <template x-for="pack in suggestedPacks" :key="pack.id">
                        <button type="button" class="cs-rx-desk__chip cs-rx-desk__chip--strong cs-rx-desk__chip--add" x-on:click="applyPack(pack)">
                            <span x-text="pack.name"></span>
                        </button>
                    </template>
                    <button type="button" class="cs-rx-desk__chip" x-show="diagnosisTests" x-on:click="applyDiagnosisTests()">
                        {{ __('Add investigations') }}
                    </button>
                </div>
            </section>

            <section class="cs-rx-desk__card">
                <h3>{{ __('Investigations') }}</h3>
                <ul class="cs-rx-desk__inv-list" x-show="investigations.length" x-cloak>
                    <template x-for="(test, index) in investigations" :key="index">
                        <li>
                            <span x-text="test"></span>
                            <button type="button" class="cs-rx-desk__remove" x-on:click="removeInvestigation(index)" title="{{ __('Remove') }}">×</button>
                        </li>
                    </template>
                </ul>
                <button type="button" class="cs-rx-desk__add" x-on:click="showAddInv = !showAddInv">
                    <span x-text="showAddInv ? '{{ __('Close') }}' : '{{ __('+ Add') }}'"></span>
                </button>
                <div class="cs-rx-desk__picker" x-show="showAddInv" x-cloak>
                    <div class="cs-rx-desk__chips">
                        <template x-for="test in investigationOptions" :key="test">
                            <button
                                type="button"
                                class="cs-rx-desk__chip"
                                :class="{ 'is-on': investigations.includes(test) }"
                                x-on:click="addInvestigation(test)"
                                x-text="test"
                            ></button>
                        </template>
                    </div>
                    <div class="cs-rx-desk__cc-custom">
                        <input
                            type="text"
                            x-model="customInvestigation"
                            x-on:keydown.enter.prevent="addCustomInvestigation()"
                            placeholder="{{ __('Or type a test and press Enter') }}"
                        >
                    </div>
                </div>
            </section>

            <section class="cs-rx-desk__card cs-rx-desk__reports-card">
                <h3>{{ __('Reports') }}</h3>
                <textarea
                    rows="2"
                    x-model="reportsSeen"
                    placeholder="{{ __('Reports the patient brought') }}"
                ></textarea>
                <div class="cs-rx-desk__report-photos">
                    <template x-for="(photo, index) in reportPhotoPreviews" :key="photo.path || index">
                        <div class="cs-rx-desk__report-thumb">
                            <img :src="photo.preview" alt="">
                            <button
                                type="button"
                                class="cs-rx-desk__remove"
                                x-show="canAttachVisitPaper"
                                x-on:click="removeReportPhoto(index)"
                                title="{{ __('Remove') }}"
                            >×</button>
                        </div>
                    </template>
                    <label
                        class="cs-rx-desk__report-add"
                        x-show="canAttachVisitPaper && reportPhotos.length < 8"
                        x-cloak
                    >
                        <span>{{ __('Scan') }}</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                            multiple
                            x-on:change="uploadReportPhoto($event)"
                        >
                    </label>
                    <label
                        class="cs-rx-desk__report-add cs-rx-desk__report-add--photo"
                        x-show="canAttachVisitPaper && reportPhotos.length < 8"
                        x-cloak
                    >
                        <span>{{ __('Take photo') }}</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                            capture="environment"
                            multiple
                            x-on:change="uploadReportPhoto($event)"
                        >
                    </label>
                </div>
                <p class="cs-rx-desk__hint" x-show="canAttachVisitPaper" x-cloak>
                    {{ __('Use the desk scanner first. Take a photo only if there is no scanner.') }}
                </p>
                <p class="cs-rx-desk__hint" x-show="!canAttachVisitPaper" x-cloak>
                    {{ __('Staff scan papers at the desk.') }}
                </p>
                <p class="cs-rx-desk__hint" x-show="reportPhotoError" x-text="reportPhotoError" x-cloak></p>
            </section>

            @if ($lastVisitRecord)
                <section class="cs-rx-desk__card cs-rx-desk__card--muted">
                    <h3>{{ __('Last visit') }}</h3>
                    <p class="cs-rx-desk__hint">
                        {{ $lastVisitRecord->booking?->booking_date?->translatedFormat('j M Y') }}
                        @if ($lastVisitRecord->diagnosisLabel())
                            · {{ $lastVisitRecord->diagnosisLabel() }}
                        @endif
                    </p>
                    <div class="cs-rx-desk__chips">
                        @if ($lastItems !== [])
                            <button type="button" class="cs-rx-desk__chip" x-on:click="copyLast()">
                                {{ __('Same medicines') }}
                            </button>
                        @endif
                        @if ($lastVisitRecord->diagnosisLabel())
                            <button type="button" class="cs-rx-desk__chip" x-on:click="copyLastDiagnosis()">
                                {{ __('Same Dx') }}
                            </button>
                        @endif
                        @if (filled($lastVisitRecord->tests_advised))
                            <button type="button" class="cs-rx-desk__chip" x-on:click="copyLastTests()">
                                {{ __('Same Inv') }}
                            </button>
                        @endif
                        @if (filled($lastVisitRecord->advice))
                            <button type="button" class="cs-rx-desk__chip" x-on:click="copyLastAdvice()">
                                {{ __('Same advice') }}
                            </button>
                        @endif
                        @if ($lastItems !== [] || filled($lastVisitRecord->advice))
                            <button type="button" class="cs-rx-desk__chip cs-rx-desk__chip--strong" x-on:click="repeatLastVisit()">
                                {{ __('Repeat whole visit') }}
                            </button>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <div class="cs-rx-desk__right">
            <section class="cs-rx-desk__card cs-rx-desk__rx">
                <h3>℞</h3>
                <div
                    class="cs-rx-safety"
                    x-show="safetyWarnings().length"
                    x-cloak
                    style="margin-bottom: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid var(--warning-300); background: color-mix(in srgb, var(--warning-50) 80%, transparent);"
                >
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--warning-800); margin-bottom: 0.35rem;">
                        {{ __('Prescription checks') }}
                    </div>
                    <template x-for="(warning, wi) in safetyWarnings()" :key="wi">
                        <div style="font-size: 0.875rem; color: var(--warning-950);" x-text="warning"></div>
                    </template>
                    {{-- Never drop this. It is what tells the doctor how much
                         weight these warnings carry, in place of a named
                         clinical reviewer. --}}
                    <div style="margin-top: 0.4rem; font-size: 0.75rem; color: var(--warning-800);">
                        {{ __(\App\Support\RxSafety::DISCLAIMER) }}
                    </div>
                </div>
                {{-- The doctor's own shortlist, one tap each. A chamber doctor
                     prescribes the same small set all day; until now those only
                     appeared if he typed. Alphabetical and capped server-side —
                     a strip that reorders itself between patients is worse than
                     no strip, and inferring order from behaviour is the thing
                     that was ruled out. --}}
                <div class="cs-rx-desk__mine" x-show="myMedicines.length" x-cloak>
                    <span class="cs-rx-desk__mine-label">{{ __('Yours') }}</span>
                    <template x-for="mine in myMedicines" :key="mine.brand_name">
                        <button
                            type="button"
                            class="cs-rx-desk__chip cs-rx-desk__chip--add"
                            x-on:click="addFromMine(mine)"
                            :title="mine.generic_name || ''"
                            x-text="mine.brand_name"
                        ></button>
                    </template>
                </div>

                <div class="cs-rx-desk__table-wrap">
                    <table class="cs-rx-desk__table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Brand') }}</th>
                                <th>{{ __('Dose') }}</th>
                                <th>{{ __('Frequency') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th>{{ __('Timing') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="item.row_key">
                                <tr
                                    :class="{ 'cs-rx-desk__row--uncertain': item.uncertain, 'is-dragging': draggingIndex === index }"
                                    x-on:dragover.prevent="dragOver(index)"
                                    x-on:drop.prevent="dropRow(index)"
                                >
                                    <td class="cs-rx-desk__index">
                                        <button
                                            type="button"
                                            class="cs-rx-desk__grip"
                                            draggable="true"
                                            x-on:dragstart="dragRow(index)"
                                            x-on:dragend="draggingIndex = null"
                                            title="{{ __('Drag to reorder') }}"
                                            aria-label="{{ __('Drag to reorder') }}"
                                        >⋮⋮</button>
                                        <span x-text="index + 1"></span>
                                    </td>
                                    <td class="cs-rx-desk__brand-cell">
                                        <input
                                            type="text"
                                            class="cs-rx-desk__brand"
                                            x-model="item.medicine_name"
                                            x-on:input.debounce.300ms="searchMedicine(index)"
                                            x-on:focus="editingCell = 'brand-' + index"
                                            x-on:blur="resolveTypedBrand(index)"
                                            x-on:keydown.arrow-down.prevent="moveBrandSuggest(1)"
                                            x-on:keydown.arrow-up.prevent="moveBrandSuggest(-1)"
                                            x-on:keydown.enter.prevent="acceptBrandSuggest(index)"
                                            x-on:keydown.escape="closeMedicineResults()"
                                            placeholder="{{ __('Type medicine…') }}"
                                        >
                                        <div class="cs-rx-desk__sub" x-show="item.generic_name" x-cloak>
                                            <span x-text="item.generic_name"></span>
                                        </div>
                                        <div class="cs-rx-desk__uncertain-hint" x-show="item.uncertain" x-cloak>
                                            {{ __('Check this row — not in your catalogue') }}
                                        </div>
                                        {{-- Why (stored as `indication`): keep the box, suggest as
                                             you type. Never a popup, never pre-filled from the
                                             catalogue's drug-class / marketing text. --}}
                                        <div class="cs-rx-desk__why" x-show="item.medicine_name" x-cloak>
                                            <label class="cs-rx-desk__why-label">{{ __('Why?') }}</label>
                                            <input
                                                type="text"
                                                class="cs-rx-desk__reason"
                                                x-model="item.indication"
                                                x-on:input.debounce.150ms="searchIndication(index)"
                                                x-on:focus="searchIndication(index)"
                                                x-on:keydown.arrow-down.prevent="moveIndicationSuggest(1)"
                                                x-on:keydown.arrow-up.prevent="moveIndicationSuggest(-1)"
                                                x-on:keydown.enter.prevent="acceptIndication(index) || nextCell($event)"
                                                x-on:keydown.escape="closeIndicationResults()"
                                                placeholder="{{ __('Reason (e.g. Pain)') }}"
                                                title="{{ __('Why this medicine — prints under the brand') }}"
                                            >
                                            <ul class="cs-rx-desk__suggest" x-show="indicationIndex === index && indicationResults.length" x-cloak>
                                                <template x-for="(row, ri) in indicationResults" :key="row.name">
                                                    <li>
                                                        <button
                                                            type="button"
                                                            :class="{ 'is-active': indicationSuggestIndex === ri }"
                                                            x-on:mousedown.prevent="pickIndication(index, row)"
                                                            x-text="row.name"
                                                        ></button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                        <ul class="cs-rx-desk__suggest" x-show="medicineResultsIndex === index && medicineResults.length" x-cloak>
                                            <template x-for="(row, ri) in medicineResults" :key="row.id">
                                                <li>
                                                    <button
                                                        type="button"
                                                        :class="{ 'is-active': brandSuggestIndex === ri }"
                                                        x-on:mousedown.prevent="pickMedicine(index, row)"
                                                    >
                                                        <strong x-text="row.brand_name"></strong>
                                                        <span x-text="row.generic_name || ''"></span>
                                                        <em x-show="row.source === 'usage'">{{ __('yours') }}</em>
                                                        <em x-show="row.source !== 'usage' && row.class_hint" x-text="row.class_hint"></em>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </td>
                                    <td>
                                        <input type="text" x-model="item.dose" x-on:focus="focusDoseCell(index)" placeholder="—" x-on:keydown.enter.prevent="nextCell($event)">
                                        <div class="cs-rx-desk__cell-chips" x-show="editingCell === 'dose-' + index && doseChipsFor(item).length" x-cloak>
                                            {{-- Label carries the form (500 mg tablet); the value written
                                                 is the bare strength, which is what prints. --}}
                                            <template x-for="chip in doseChipsFor(item)" :key="chip.value + chip.label">
                                                <button type="button" class="cs-rx-desk__chip" x-on:click="setDose(index, chip.value)" x-text="chip.label"></button>
                                            </template>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" x-model="item.frequency" x-on:focus="editingCell = 'freq-' + index" placeholder="—" x-on:keydown.enter.prevent="nextCell($event)">
                                        <div class="cs-rx-desk__cell-chips" x-show="editingCell === 'freq-' + index" x-cloak>
                                            <template x-for="chip in frequencyPresets" :key="chip">
                                                <button type="button" class="cs-rx-desk__chip" x-on:click="item.frequency = chip" x-text="chip"></button>
                                            </template>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" x-model="item.duration" x-on:focus="editingCell = 'dur-' + index" placeholder="—" x-on:keydown.enter.prevent="nextCell($event)">
                                        <div class="cs-rx-desk__cell-chips" x-show="editingCell === 'dur-' + index" x-cloak>
                                            <template x-for="chip in durationPresets" :key="chip">
                                                <button type="button" class="cs-rx-desk__chip" x-on:click="item.duration = chip" x-text="chip"></button>
                                            </template>
                                        </div>
                                    </td>
                                    <td>
                                        {{-- Timing options are Blade-rendered, not Alpine x-for.
                                             Prefill sets item.timing = 'after_food' the same way it
                                             sets frequency — but a <select> whose <option>s are built
                                             by x-for briefly has no matching option, the browser
                                             falls back to "—", and x-model writes '' back over the
                                             prefill. Text inputs never do that, which is why dose /
                                             frequency / duration stuck and timing never did. --}}
                                        <select x-model="item.timing" x-on:focus="editingCell = 'timing-' + index">
                                            <option value="">—</option>
                                            @foreach ($timingOptions as $opt)
                                                <option value="{{ $opt['key'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <div class="cs-rx-desk__cell-chips" x-show="editingCell === 'timing-' + index" x-cloak>
                                            <template x-for="opt in timingOptions" :key="'chip-' + opt.key">
                                                <button type="button" class="cs-rx-desk__chip" x-on:click="item.timing = opt.key" x-text="opt.label"></button>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="cs-rx-desk__row-actions">
                                        <button
                                            type="button"
                                            class="cs-rx-desk__save-default"
                                            x-show="item.medicine_name"
                                            :class="{ 'is-saved': item.saved_default }"
                                            x-on:click="saveAsDefault(index)"
                                            :title="item.saved_default ? '{{ __('Saved to My medicines') }}' : '{{ __('Save this line as my default for this medicine') }}'"
                                            x-cloak
                                        >★</button>
                                        {{-- Order is not cosmetic: it is the order
                                             the patient reads and the pharmacist
                                             dispenses. Without these, a drug added
                                             out of sequence had to be deleted and
                                             retyped. --}}
                                        <button
                                            type="button"
                                            class="cs-rx-desk__move"
                                            x-show="index > 0"
                                            x-on:click="moveItem(index, -1)"
                                            title="{{ __('Move up') }}"
                                            x-cloak
                                        >↑</button>
                                        <button
                                            type="button"
                                            class="cs-rx-desk__move"
                                            x-show="index < items.length - 1"
                                            x-on:click="moveItem(index, 1)"
                                            title="{{ __('Move down') }}"
                                            x-cloak
                                        >↓</button>
                                        <button type="button" class="cs-rx-desk__remove" x-on:click="removeItem(index)" title="{{ __('Remove') }}">×</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- The typing box searches the catalogue as you type. Enter on
                     a highlighted suggestion, or on a name the catalogue knows
                     exactly, adds a row already carrying the dose, frequency,
                     duration and timing — anything typed after the name still
                     wins over the prefill. --}}
                <div class="cs-rx-desk__shorthand">
                    <input
                        type="text"
                        x-model="shorthand"
                        x-on:input.debounce.250ms="searchShorthand()"
                        x-on:keydown.arrow-down.prevent="moveShorthand(1)"
                        x-on:keydown.arrow-up.prevent="moveShorthand(-1)"
                        x-on:keydown.escape="closeShorthandResults()"
                        x-on:keydown.enter.prevent="commitShorthand()"
                        placeholder="{{ __('napa 500 1+0+1 5d af — Enter to add') }}"
                    >
                    {{-- "Use a pack", not "Packs": this screen applies them and
                         cannot build them, and the old label invited the doctor
                         to look here for a way to make one. --}}
                    <button type="button" class="cs-rx-desk__link" x-on:click="packMenuOpen = !packMenuOpen">{{ __('Use a pack') }}</button>
                </div>
                <ul class="cs-rx-desk__suggest" x-show="shorthandResults.length" x-cloak>
                    <template x-for="(row, si) in shorthandResults" :key="row.id">
                        <li>
                            <button
                                type="button"
                                :class="{ 'is-active': shorthandIndex === si }"
                                x-on:click="commitShorthand(row)"
                            >
                                <strong x-text="row.brand_name"></strong>
                                <span x-text="row.generic_name || ''"></span>
                                <em x-show="row.source === 'usage'">{{ __('yours') }}</em>
                                <em x-show="row.source !== 'usage' && row.class_hint" x-text="row.class_hint"></em>
                            </button>
                        </li>
                    </template>
                </ul>

                {{-- Under the table, not beside the typing box: it is the action
                     that continues the list, so it belongs where the list ends. --}}
                <div class="cs-rx-desk__add-row">
                    <button type="button" class="cs-rx-desk__add-btn" x-on:click="addEmpty()">
                        {{ __('+ Add medicine') }}
                    </button>
                </div>

                <div class="cs-rx-desk__packs" x-show="packMenuOpen" x-cloak>
                    <div class="cs-rx-desk__chips" x-show="packs.length">
                        <template x-for="pack in packs" :key="pack.id">
                            <button type="button" class="cs-rx-desk__chip cs-rx-desk__chip--add" x-on:click="applyPack(pack)" x-text="pack.name"></button>
                        </template>
                    </div>
                    {{-- Packs are applied here, never built here. Naming and
                         saving one is admin work, and doing it with a patient
                         in the chair is not what this screen is for — they are
                         made on My medicines instead (owner decision). --}}
                    <p class="cs-rx-desk__hint" x-show="!packs.length">
                        {{ __('No packs yet. Build them on My medicines, then they are one tap here.') }}
                    </p>
                </div>
            </section>

            <section class="cs-rx-desk__card">
                <h3>{{ __('Advice') }}</h3>
                <div class="cs-rx-desk__chips">
                    @foreach ($adviceChips as $chip)
                        <button
                            type="button"
                            class="cs-rx-desk__chip cs-rx-desk__chip--add"
                            data-text="{{ $chip['text'] }}"
                            x-on:click="applyAdviceChip($el.dataset.text)"
                        >{{ $chip['label'] }}</button>
                    @endforeach
                    {{-- Starred during this consult: already saved to My
                         medicines, but this pad was rendered before it existed. --}}
                    <template x-for="line in myAdvice" :key="line">
                        <button
                            type="button"
                            class="cs-rx-desk__chip cs-rx-desk__chip--add"
                            x-on:click="applyAdviceChip(line)"
                            x-text="line"
                        ></button>
                    </template>
                    <button
                        type="button"
                        class="cs-rx-desk__chip cs-rx-desk__chip--add cs-rx-desk__chip--advice"
                        x-show="diagnosisAdvice"
                        x-on:click="applyDiagnosisAdvice()"
                        :title="diagnosisAdvice"
                        x-cloak
                    >
                        <span x-text="diagnosisAdvicePreview"></span>
                    </button>
                    <button
                        type="button"
                        class="cs-rx-desk__save-default"
                        x-show="(advice || '').trim()"
                        x-on:click="saveAdviceAsMine()"
                        title="{{ __('Save this advice') }}"
                        x-cloak
                    >★</button>
                </div>
                <textarea rows="2" x-model="advice"></textarea>
            </section>

            {{-- 3 months and a date box are not extras: the phone modal has
                 always had both, so a doctor who wanted six weeks or a yearly
                 review could say it on a phone and not at the desk. --}}
            <section class="cs-rx-desk__card">
                <h3>{{ __('Follow-up') }}</h3>
                <div class="cs-rx-desk__chips">
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === '1_week' }" x-on:click="setFollowUp('1_week')">{{ __('1 week') }}</button>
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === '2_weeks' }" x-on:click="setFollowUp('2_weeks')">{{ __('2 weeks') }}</button>
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === '1_month' }" x-on:click="setFollowUp('1_month')">{{ __('1 month') }}</button>
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === '3_months' }" x-on:click="setFollowUp('3_months')">{{ __('3 months') }}</button>
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === 'as_needed' }" x-on:click="setFollowUp('as_needed')">{{ __('As needed') }}</button>
                    <button type="button" class="cs-rx-desk__chip" :class="{ 'is-on': followUpRelative === 'pick_date' }" x-on:click="setFollowUp('pick_date')">{{ __('Pick a date') }}</button>
                </div>
                <input
                    type="date"
                    x-model="followUpDate"
                    x-show="followUpRelative === 'pick_date'"
                    min="{{ now()->toDateString() }}"
                    aria-label="{{ __('Follow-up date') }}"
                    x-cloak
                >
                <input
                    type="text"
                    x-model="followUpNote"
                    x-show="followUpRelative === 'as_needed'"
                    maxlength="255"
                    placeholder="{{ __('e.g. Come back if fever continues') }}"
                    aria-label="{{ __('Follow-up note') }}"
                    x-cloak
                >
            </section>
        </div>
    </div>
</div>

@once
    @script
    <script>
        Alpine.data('rxDesk', (config) => ({
            items: Array.isArray(config.items) ? config.items.map((row) => ({ ...row, timing: row.timing || '', row_key: row.row_key || ('r' + Math.random().toString(36).slice(2)) })) : [],
            lastItems: config.lastItems || [],
            complaints: Array.isArray(config.complaints) ? config.complaints : [],
            complaintDurations: Array.isArray(config.complaintDurations) ? config.complaintDurations : [],
            customComplaint: '',
            historyChips: Array.isArray(config.historyChips) ? config.historyChips : [],
            historyPrimary: Array.isArray(config.historyPrimary) ? config.historyPrimary : [],
            historySelected: {},
            historyNote: '',
            investigations: Array.isArray(config.investigations) ? config.investigations : [],
            investigationOptions: Array.isArray(config.investigationOptions) ? config.investigationOptions : [],
            customInvestigation: '',
            adviceChips: Array.isArray(config.adviceChips) ? config.adviceChips : [],
            findingChips: Array.isArray(config.findingChips) ? config.findingChips : [],
            indicationCommon: Array.isArray(config.indicationCommon) ? config.indicationCommon : [],
            myAdvice: [],
            onExamination: config.onExamination || '',
            diagnosis: config.diagnosis || '',
            diagnosisLabel: config.diagnosisLabel || '',
            diagnosisQuery: '',
            diagnosisResults: [],
            weightKg: config.weightKg ?? '',
            bpSystolic: config.bpSystolic ?? '',
            bpDiastolic: config.bpDiastolic ?? '',
            pulseBpm: config.pulseBpm ?? '',
            spo2Percent: config.spo2Percent ?? '',
            temperatureF: config.temperatureF ?? '',
            lastWeightKg: config.lastWeightKg ?? null,
            lastBpSystolic: config.lastBpSystolic ?? null,
            lastBpDiastolic: config.lastBpDiastolic ?? null,
            lastPulseBpm: config.lastPulseBpm ?? null,
            lastSpo2Percent: config.lastSpo2Percent ?? null,
            lastTemperatureF: config.lastTemperatureF ?? null,
            advice: config.advice || '',
            reportsSeen: config.reportsSeen || '',
            reportPhotos: Array.isArray(config.reportPhotos) ? config.reportPhotos : [],
            reportPhotoPreviews: Array.isArray(config.reportPhotoPreviews) ? config.reportPhotoPreviews : [],
            reportPhotoUploadUrl: config.reportPhotoUploadUrl || '',
            canAttachVisitPaper: Boolean(config.canAttachVisitPaper),
            reportPhotoError: '',
            followUpRelative: config.followUpRelative || '',
            followUpDate: config.followUpDate || '',
            followUpNote: config.followUpNote || '',
            voicePath: config.voicePath || '',
            voiceTranscript: config.voiceTranscript || '',
            clinicalNotes: config.clinicalNotes || '',
            frequencyPresets: config.frequencyPresets || [],
            durationPresets: config.durationPresets || [],
            timingOptions: config.timingOptions || [],
            medicineSearchUrl: config.medicineSearchUrl,
            medicineDosesUrl: config.medicineDosesUrl,
            conditionSearchUrl: config.conditionSearchUrl,
            // brand -> [{ value, label }], filled from the catalogue on demand.
            doseOptions: {},
            // brand -> { dose, frequency, duration, timing } from the SKU that
            // actually carries defaults (tablet), not from drops/injection.
            brandDefaults: {},
            freeDiagnosisPrefix: config.freeDiagnosisPrefix,
            lastDiagnosis: config.lastDiagnosis || '',
            lastDiagnosisLabel: config.lastDiagnosisLabel || '',
            lastTestsAdvised: config.lastTestsAdvised || '',
            lastAdvice: config.lastAdvice || '',
            historyFromRecord: false,
            packs: config.packs || [],
            myMedicines: Array.isArray(config.myMedicines) ? config.myMedicines : [],
            packMenuOpen: false,
            patientAllergies: config.patientAllergies || '',
            bookingId: config.bookingId || null,
            patientName: config.patientName || '',
            patientAge: config.patientAge || '',
            diagnosisAdvice: config.diagnosisAdvice || '',
            diagnosisTests: config.diagnosisTests || '',
            medicineResults: [],
            medicineResultsIndex: null,
            brandSuggestIndex: -1,
            editingCell: null,
            shorthand: '',
            shorthandResults: [],
            shorthandIndex: -1,
            showAllComplaints: false,
            showAddCc: false,
            showAddInv: false,
            showMoreHistory: false,
            onMyPaper: false,
            draggingIndex: null,
            indicationResults: [],
            indicationIndex: null,
            indicationSuggestIndex: -1,

            // Draft state. `savedSignature` is the payload as the server last
            // saw it; `clean` means the doctor has not touched the pad since it
            // opened, which is the one state that shows no badge at all.
            savedSignature: '',
            saveState: 'clean',
            draftTimer: null,
            draftInFlight: false,

            init() {
                try {
                    this.onMyPaper = window.localStorage.getItem('cq-print-on-my-paper') === '1';
                } catch (e) {
                    this.onMyPaper = false;
                }
                if (this.onMyPaper) {
                    $wire.set('printOnMyPaper', true);
                }
                // A pad that opens as bare column headings reads as broken and
                // gives the doctor nothing to type into. One blank row is the
                // paper pad's own resting state; it is dropped on save if it
                // is still empty.
                if (!this.items.length) {
                    this.items.push(this.emptyItem());
                }
                this.historySelected = Object.fromEntries(
                    this.historyChips.map((chip) => [chip, false])
                );
                const source = (config.history || config.historySeed || '').trim();
                if (source) {
                    let note = source;
                    this.historyChips.forEach((chip) => {
                        const pattern = new RegExp('(^|[,·\\s])' + chip.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=([,·\\s]|$))', 'i');
                        if (pattern.test(source)) {
                            this.historySelected[chip] = true;
                            note = note.replace(new RegExp('\\b' + chip.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b[,\\s]*', 'gi'), '');
                        }
                    });
                    this.historyNote = note.replace(/^[·,\s]+|[·,\s]+$/g, '').replace(/\s*·\s*/g, ' · ').trim();
                    this.historyFromRecord = !config.history && !!config.historySeed;
                }

                // Snapshot last, after the waiting row and the carried-over H/O
                // are in place, so neither counts as the doctor having written
                // something. A pad nobody touched still saves nothing, and
                // "N previous visits · no notes recorded" stays honest.
                this.savedSignature = this.signature();
            },

            /**
             * One writer for the follow-up chips so the two free-text
             * companions can never both be live at once — a stored note next
             * to a stored date is two different answers to "come back when?".
             */
            setFollowUp(relative) {
                this.followUpRelative = relative;

                if (relative !== 'as_needed') {
                    this.followUpNote = '';
                }
                if (relative !== 'pick_date') {
                    this.followUpDate = '';
                }
            },

            signature() {
                return JSON.stringify(this.payload());
            },

            isDirty() {
                return this.signature() !== this.savedSignature;
            },

            /**
             * Runs from `x-effect`, so it re-evaluates whenever anything the
             * payload reads changes — one hook instead of a watcher per field,
             * and a new field cannot forget to opt in.
             */
            queueDraftSave() {
                if (!this.isDirty()) {
                    return;
                }
                if (this.saveState !== 'saving') {
                    this.saveState = 'unsaved';
                }
                clearTimeout(this.draftTimer);
                this.draftTimer = setTimeout(() => this.flush(), 1500);
            },

            /**
             * Clicking anywhere off the pad is the moment the doctor is most
             * likely to be reaching for **Complete visit** — a page header
             * action that reads the *stored* record. Livewire serialises calls
             * on this component, so a flush fired on pointerdown lands before
             * the button's own round trip mounts the form.
             */
            flushIfClickedAway(event) {
                if (this.$el.contains(event.target)) {
                    return;
                }
                this.flush();
            },

            async flush() {
                clearTimeout(this.draftTimer);

                if (this.draftInFlight || !this.isDirty()) {
                    return;
                }

                const payload = this.payload();
                const signature = JSON.stringify(payload);

                this.draftInFlight = true;
                this.saveState = 'saving';

                try {
                    await $wire.autosaveRxDesk(payload);
                    this.savedSignature = signature;
                    this.saveState = 'saved';
                } catch (e) {
                    // Say Unsaved *before* touching the outbox. Setting it
                    // afterwards left the badge stuck on "Saving…" whenever
                    // enqueue threw or hung — telling the doctor a save was
                    // still in flight when it had already failed, which is the
                    // one lie this indicator exists to prevent.
                    this.saveState = 'unsaved';

                    if (window.ChamberQOffline) {
                        try {
                            await window.ChamberQOffline.enqueue({
                                type: 'rx_save',
                                booking_id: this.bookingId,
                                data: payload,
                            });
                        } catch (queueError) {
                            // Nothing more to try. The pad still holds the work
                            // and still says Unsaved.
                        }
                    }
                } finally {
                    this.draftInFlight = false;
                }
            },

            rememberOnMyPaper() {
                try {
                    window.localStorage.setItem('cq-print-on-my-paper', this.onMyPaper ? '1' : '0');
                } catch (e) {}
                $wire.set('printOnMyPaper', this.onMyPaper);
            },
            timingShorthand: { af: 'after_food', ac: 'before_food', bf: 'before_food', pc: 'after_food', es: 'empty_stomach', hs: 'at_night', an: 'at_night', wf: 'with_food' },

            emptyItem() {
                return {
                    row_key: 'r' + Math.random().toString(36).slice(2),
                    medicine_name: '',
                    generic_name: '',
                    indication: '',
                    dose: '',
                    frequency: '',
                    duration: '',
                    timing: '',
                    instructions: '',
                    prefilled: false,
                    saved_default: false,
                    uncertain: false,
                };
            },

            addEmpty() {
                this.items.push(this.emptyItem());
            },

            /**
             * Move a medicine up or down the prescription.
             *
             * `splice` twice rather than swapping by index: Alpine tracks these
             * rows by their position in the array, and reassigning two elements
             * in place leaves the inputs bound to the old row.
             */
            moveItem(index, direction) {
                const to = index + direction;
                if (to < 0 || to >= this.items.length) return;

                const [row] = this.items.splice(index, 1);
                this.items.splice(to, 0, row);
            },

            dragRow(index) {
                this.draggingIndex = index;
            },

            dragOver(index) {
                if (this.draggingIndex === null || this.draggingIndex === index) return;
                const [row] = this.items.splice(this.draggingIndex, 1);
                this.items.splice(index, 0, row);
                this.draggingIndex = index;
            },

            dropRow() {
                this.draggingIndex = null;
            },

            removeItem(index) {
                this.items.splice(index, 1);
            },

            copyLast() {
                if (!this.lastItems.length) return;
                this.items = this.lastItems.map((row) => ({ ...this.emptyItem(), ...row, timing: row.timing || '' }));
            },

            copyLastDiagnosis() {
                if (!this.lastDiagnosis) return;
                this.diagnosis = this.lastDiagnosis;
                this.diagnosisLabel = this.lastDiagnosisLabel;
            },

            copyLastTests() {
                if (!this.lastTestsAdvised) return;
                const incoming = this.lastTestsAdvised.split(/[,;\n]+/).map((s) => s.trim()).filter(Boolean);
                incoming.forEach((test) => this.addInvestigation(test));
            },

            copyLastAdvice() {
                if (this.lastAdvice) this.advice = this.lastAdvice;
            },

            repeatLastVisit() {
                this.copyLast();
                this.copyLastDiagnosis();
                this.copyLastTests();
                this.copyLastAdvice();
            },

            appendComplaint(chip) {
                const name = (chip || '').trim();
                if (!name || this.hasComplaint(name)) {
                    return;
                }
                this.complaints.push({ complaint: name, duration: '' });
                this.showAddCc = false;
            },

            addCustomComplaint() {
                this.appendComplaint(this.customComplaint);
                this.customComplaint = '';
            },

            hasComplaint(chip) {
                const needle = (chip || '').trim().toLowerCase();

                return this.complaints.some(
                    (row) => (row.complaint || '').trim().toLowerCase() === needle
                );
            },

            setComplaintDuration(index, duration) {
                const row = this.complaints[index];
                if (!row) {
                    return;
                }
                row.duration = row.duration === duration ? '' : duration;
            },

            removeComplaint(index) {
                this.complaints.splice(index, 1);
            },

            formatComplaints() {
                return this.complaints
                    .map((row) => {
                        const complaint = (row.complaint || '').trim();
                        if (!complaint) {
                            return '';
                        }
                        const duration = (row.duration || '').trim();

                        return duration ? `${complaint} — ${duration}` : complaint;
                    })
                    .filter(Boolean)
                    .join('\n');
            },

            toggleHistory(chip) {
                this.historySelected[chip] = !this.historySelected[chip];
                this.historyFromRecord = false;
            },

            formatHistory() {
                const chips = this.historyChips.filter((chip) => this.historySelected[chip]);
                const note = (this.historyNote || '').trim();
                if (chips.length && note) {
                    return `${chips.join(', ')} · ${note}`;
                }
                if (chips.length) {
                    return chips.join(', ');
                }

                return note;
            },

            findingParts() {
                return (this.onExamination || '')
                    .split(/\s*[·,]\s*/)
                    .map((part) => part.trim())
                    .filter(Boolean);
            },

            hasFinding(chip) {
                const needle = (chip || '').trim().toLowerCase();

                return this.findingParts().some((part) => part.toLowerCase() === needle);
            },

            toggleFinding(chip) {
                const name = (chip || '').trim();
                if (!name) return;
                const parts = this.findingParts();
                const next = this.hasFinding(name)
                    ? parts.filter((part) => part.toLowerCase() !== name.toLowerCase())
                    : [...parts, name];
                this.onExamination = next.join(' · ');
            },

            applyAdviceChip(text) {
                const line = (text || '').trim();
                if (!line) return;
                const current = (this.advice || '').trim();
                if (current.includes(line)) return;
                this.advice = current ? `${current}\n${line}` : line;
            },

            /**
             * The ★ on the Advice card: keep this line as a chip.
             *
             * It used to live in this browser's localStorage, so a doctor who
             * saw patients from the chamber desk and then from his own laptop
             * had two different sets of "my advice" and no way to edit either.
             * It is now a row on My medicines, saved for the doctor rather than
             * for the machine.
             */
            saveAdviceAsMine() {
                const line = (this.advice || '').trim().split('\n').pop()?.trim();
                if (!line) return;
                if (this.myAdvice.includes(line)) return;
                if (this.adviceChips.some((chip) => (chip.text || '') === line)) return;
                this.myAdvice = [line, ...this.myAdvice];
                $wire.saveAdviceAsMine(line);
            },

            addInvestigation(test) {
                const name = (test || '').trim();
                if (!name || this.investigations.includes(name)) {
                    return;
                }
                this.investigations.push(name);
            },

            addCustomInvestigation() {
                this.addInvestigation(this.customInvestigation);
                this.customInvestigation = '';
                this.showAddInv = false;
            },

            removeInvestigation(index) {
                this.investigations.splice(index, 1);
            },

            formatInvestigations() {
                return this.investigations.filter(Boolean).join(', ');
            },

            async uploadReportPhoto(event) {
                const input = event.target;
                const files = Array.from(input.files || []);
                input.value = '';
                this.reportPhotoError = '';

                for (const file of files) {
                    if (this.reportPhotos.length >= 8) {
                        break;
                    }
                    await this.sendReportPhoto(file);
                }
            },

            async sendReportPhoto(file) {
                if (!this.canAttachVisitPaper || !this.reportPhotoUploadUrl) {
                    return;
                }

                const preview = URL.createObjectURL(file);
                const formData = new FormData();
                formData.append('photo', file);

                try {
                    const response = await fetch(this.reportPhotoUploadUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || @js(__('Upload failed.')));
                    }

                    const payload = await response.json();
                    this.reportPhotos.push(payload.path);
                    this.reportPhotoPreviews.push({ path: payload.path, preview });
                } catch (error) {
                    URL.revokeObjectURL(preview);
                    this.reportPhotoError = error.message || @js(__('Upload failed.'));
                }
            },

            removeReportPhoto(index) {
                const row = this.reportPhotoPreviews[index];
                if (row?.preview && String(row.preview).startsWith('blob:')) {
                    URL.revokeObjectURL(row.preview);
                }
                this.reportPhotos.splice(index, 1);
                this.reportPhotoPreviews.splice(index, 1);
            },

            clearDiagnosis() {
                this.diagnosis = '';
                this.diagnosisLabel = '';
                this.diagnosisAdvice = '';
                this.diagnosisTests = '';
            },

            pickDiagnosis(row) {
                this.diagnosis = row.id;
                this.diagnosisLabel = row.name;
                this.diagnosisQuery = '';
                this.diagnosisResults = [];
                this.diagnosisAdvice = row.advice || '';
                this.diagnosisTests = row.tests || '';
            },

            get suggestedPacks() {
                if (!this.diagnosis) return [];
                return this.packs.filter((pack) => pack.condition_id === this.diagnosis);
            },

            get diagnosisAdvicePreview() {
                const text = (this.diagnosisAdvice || '').replace(/\s+/g, ' ').trim();
                if (!text) return '';

                return text.length > 80 ? `${text.slice(0, 80).trim()}…` : text;
            },

            /**
             * Fills, never wipes. Medicines already on the pad stay, the
             * pack's own lines are appended, and a text box the doctor has
             * written in is left alone.
             */
            applyPack(pack) {
                const existing = this.items
                    .map((row) => (row.medicine_name || '').trim().toUpperCase())
                    .filter(Boolean);

                (pack.items || []).forEach((row) => {
                    if (existing.includes((row.medicine_name || '').trim().toUpperCase())) return;
                    this.items.push({ ...this.emptyItem(), ...row, timing: row.timing || '' });
                });

                this.items = this.items.filter((row) => (row.medicine_name || '').trim() !== '');
                if (!this.advice && pack.advice) this.advice = pack.advice;
                if (pack.tests_advised) {
                    pack.tests_advised.split(/[,;\n]+/).map((s) => s.trim()).filter(Boolean)
                        .forEach((test) => this.addInvestigation(test));
                }
                if (!this.followUpRelative && pack.follow_up_relative) this.followUpRelative = pack.follow_up_relative;
                this.packMenuOpen = false;
            },

            applyDiagnosisAdvice() {
                if (!this.diagnosisAdvice) return;
                const current = (this.advice || '').trim();
                if (current.includes(this.diagnosisAdvice)) return;
                this.advice = current ? `${current}\n${this.diagnosisAdvice}` : this.diagnosisAdvice;
            },

            applyDiagnosisTests() {
                if (!this.diagnosisTests) return;
                this.diagnosisTests.split(/[,;\n]+/).map((s) => s.trim()).filter(Boolean)
                    .forEach((test) => this.addInvestigation(test));
            },

            closeIndicationResults() {
                this.indicationResults = [];
                this.indicationIndex = null;
                this.indicationSuggestIndex = -1;
            },

            matchIndicationCommon(query) {
                const needle = (query || '').trim().toLowerCase();
                if (!needle) return [];

                return this.indicationCommon
                    .map((name) => {
                        const term = String(name).toLowerCase();
                        let score = 0;
                        if (term === needle) score = 100;
                        else if (term.startsWith(needle)) score = 80;
                        else if (term.includes(needle)) score = 60;
                        return score ? { name, score } : null;
                    })
                    .filter(Boolean)
                    .sort((a, b) => b.score - a.score || a.name.localeCompare(b.name))
                    .slice(0, 8)
                    .map((row) => ({ name: row.name }));
            },

            async searchIndication(index) {
                const q = (this.items[index]?.indication || '').trim();
                if (q.length < 1) {
                    this.closeIndicationResults();
                    return;
                }

                const local = this.matchIndicationCommon(q);
                const seen = new Set(local.map((row) => row.name.toLowerCase()));
                this.indicationIndex = index;
                this.indicationSuggestIndex = local.length ? 0 : -1;
                this.indicationResults = local;

                if (q.length < 3 || !this.conditionSearchUrl) {
                    return;
                }

                try {
                    const res = await fetch(`${this.conditionSearchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const extra = (data.results || [])
                        .map((row) => ({ name: row.name || row.label || '' }))
                        .filter((row) => row.name && !seen.has(row.name.toLowerCase()));
                    extra.forEach((row) => seen.add(row.name.toLowerCase()));
                    this.indicationResults = [...local, ...extra].slice(0, 8);
                    if (this.indicationSuggestIndex < 0 && this.indicationResults.length) {
                        this.indicationSuggestIndex = 0;
                    }
                } catch (e) {}
            },

            moveIndicationSuggest(step) {
                if (this.indicationIndex === null || !this.indicationResults.length) return;
                const next = this.indicationSuggestIndex + step;
                this.indicationSuggestIndex = next < 0
                    ? this.indicationResults.length - 1
                    : next % this.indicationResults.length;
            },

            pickIndication(index, row) {
                const item = this.items[index];
                if (!item || !row?.name) return;
                item.indication = row.name;
                this.closeIndicationResults();
            },

            acceptIndication(index) {
                if (this.indicationIndex === index && this.indicationSuggestIndex >= 0) {
                    this.pickIndication(index, this.indicationResults[this.indicationSuggestIndex]);
                    return true;
                }
                this.closeIndicationResults();
                return false;
            },

            /**
             * The strengths this brand actually ships in — nothing else.
             *
             * These used to be a fixed 500/10/20/40/5 mg list shown for every
             * drug alike, which offered a 5 mg NAPA nobody manufactures and
             * hid the 120 mg/5 ml syrup that exists. The catalogue holds one
             * row per brand + strength + form, so it already knows the answer;
             * `ensureDoseOptions()` fetches it for the brand on this row.
             */
            doseChipsFor(item) {
                const brand = (item.medicine_name || '').trim().toUpperCase();
                const options = this.doseOptions[brand] || [];

                return options;
            },

            /**
             * Load a brand's strengths once, then serve them from memory.
             *
             * Called both when a medicine is picked from search and when the
             * doctor focuses the dose cell — a row reopened from a saved
             * prescription never ran a search, so focus is the only moment
             * that can fill it.
             */
            async ensureDoseOptions(brand) {
                const key = (brand || '').trim().toUpperCase();
                if (!key || this.doseOptions[key]) {
                    return;
                }

                // Claim the key before awaiting so two quick focuses on the
                // same brand do not both fetch.
                this.doseOptions[key] = [];

                try {
                    const res = await fetch(`${this.medicineDosesUrl}?brand=${encodeURIComponent(key)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.doseOptions[key] = data.options || [];
                    this.brandDefaults[key] = data.defaults || {};
                } catch (e) {
                    this.doseOptions[key] = [];
                    this.brandDefaults[key] = {};
                }
            },

            /**
             * Fill empty frequency / duration / timing from the brand's usual
             * line. Choosing the paediatric drops strength must not wipe the
             * adult tablet's 1+1+1 / 3 days / after food — those cells stay
             * empty only when the catalogue truly has nothing for the brand.
             */
            applyBrandDefaults(item) {
                const defaults = this.brandDefaults[(item.medicine_name || '').trim().toUpperCase()] || {};
                this.applyPrefill(item, defaults);
            },

            async setDose(index, value) {
                const item = this.items[index];
                if (!item) return;
                item.dose = value;
                await this.ensureDoseOptions(item.medicine_name);
                this.applyBrandDefaults(item);
            },

            focusDoseCell(index) {
                this.editingCell = 'dose-' + index;
                this.ensureDoseOptions(this.items[index]?.medicine_name);
            },

            async searchMedicine(index) {
                const q = (this.items[index]?.medicine_name || '').trim();
                if (q.length < 2) {
                    this.closeMedicineResults();
                    return;
                }
                try {
                    const res = await fetch(`${this.medicineSearchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        this.medicineResults = window.ChamberQOffline?.searchMedicines(q) || [];
                        this.medicineResultsIndex = index;
                        this.brandSuggestIndex = -1;
                        return;
                    }
                    const data = await res.json();
                    this.medicineResults = data.results || [];
                    this.medicineResultsIndex = index;
                    this.brandSuggestIndex = -1;
                    window.ChamberQOffline?.rememberSearchResults(this.medicineResults);
                } catch (e) {
                    this.medicineResults = window.ChamberQOffline?.searchMedicines(q) || [];
                    this.medicineResultsIndex = index;
                    this.brandSuggestIndex = -1;
                }
            },

            closeMedicineResults() {
                this.medicineResults = [];
                this.medicineResultsIndex = null;
                this.brandSuggestIndex = -1;
            },

            moveBrandSuggest(step) {
                if (this.medicineResultsIndex === null || !this.medicineResults.length) return;
                const next = this.brandSuggestIndex + step;
                this.brandSuggestIndex = next < 0
                    ? this.medicineResults.length - 1
                    : next % this.medicineResults.length;
            },

            acceptBrandSuggest(index) {
                if (this.medicineResultsIndex === index && this.brandSuggestIndex >= 0) {
                    this.pickMedicine(index, this.medicineResults[this.brandSuggestIndex]);
                    return;
                }
                // Enter with no highlight: keep what was typed and try an exact
                // catalogue match so "NAPA" still prefills without a mouse click.
                this.resolveTypedBrand(index);
            },

            /**
             * Enter moves to the next cell, so a prescription can be written
             * without reaching for the mouse.
             *
             * Walks the real inputs in the table rather than tracking row and
             * column indices: cells appear and disappear (the Reason box only
             * exists once a brand is chosen), and an index-based map would go
             * stale exactly when the doctor is typing fastest. Off the end of
             * the last row it adds a new one, which is what Enter means on a
             * paper pad.
             */
            nextCell(event) {
                const table = event.target.closest('table');
                if (!table) return;

                const visible = () => Array.from(table.querySelectorAll('input, select'))
                    .filter((el) => !el.disabled && el.offsetParent !== null);

                const cells = visible();
                const at = cells.indexOf(event.target);
                if (at === -1) return;

                if (at < cells.length - 1) {
                    cells[at + 1].focus();
                    return;
                }

                this.addEmpty();
                this.$nextTick(() => visible()[at + 1]?.focus());
            },

            /**
             * Doctor typed a full brand and tabbed out without picking a row.
             *
             * Prefill only on an exact brand match — same rule as the typing
             * box — so a half-name never silently becomes a different drug.
             */
            async resolveTypedBrand(index) {
                const item = this.items[index];
                if (!item) return;

                // Let a mousedown on a suggestion land first; blur fires before click.
                await new Promise((resolve) => setTimeout(resolve, 150));
                if (this.medicineResultsIndex === index && this.medicineResults.length && this.brandSuggestIndex >= 0) {
                    return;
                }

                const name = (item.medicine_name || '').trim();
                if (!name) {
                    this.closeMedicineResults();
                    return;
                }

                // Already filled from a pick — leave the doctor's choices alone.
                if (item.prefilled || item.generic_name || item.dose || item.frequency) {
                    this.closeMedicineResults();
                    return;
                }

                const match = await this.catalogueMatch(name);
                if (match) {
                    item.medicine_name = match.brand_name || item.medicine_name;
                    this.applyPrefill(item, match);
                    await this.fillOnlyStrength(item);
                }
                this.closeMedicineResults();
            },

            /**
             * Carry a catalogue/search row onto a pad line.
             *
             * The search row arrives already resolved through the prefill
             * layers, so a filled value here is either this doctor's own saved
             * default or the catalogue's. Cells already typed in are never
             * overwritten — the doctor's own keystrokes outrank any default.
             */
            /**
             * One tap from the doctor's own shortlist.
             *
             * His saved line wins outright — that is the whole point of having
             * curated it — so this fills from the entry rather than going back
             * to the catalogue. `fillOnlyStrength()` still runs afterwards to
             * load the brand's real dose chips, and to fill a strength if he
             * never saved one and the brand ships in only one.
             *
             * Already on the prescription? Do nothing rather than add a second
             * line: the duplicate warning would fire on a row the doctor did
             * not mean to create.
             */
            async addFromMine(mine) {
                const brand = (mine.brand_name || '').trim().toUpperCase();
                if (!brand) return;

                const already = this.items.some(
                    (line) => (line.medicine_name || '').trim().toUpperCase() === brand
                );
                if (already) return;

                const item = this.emptyItem();
                item.medicine_name = brand;
                this.applyPrefill(item, mine);
                item.saved_default = true;

                // Reuse the waiting blank line, same as the typing box does,
                // so tapping never leaves an empty row above the medicine.
                const blank = this.items.findIndex((line) => !(line.medicine_name || '').trim());
                if (blank === -1) {
                    this.items.push(item);
                } else {
                    this.items.splice(blank, 1, item);
                }

                const at = blank === -1 ? this.items.length - 1 : blank;
                await this.fillOnlyStrength(this.items[at]);
            },

            applyPrefill(item, row) {
                if (!item.generic_name && row.generic_name) item.generic_name = row.generic_name;
                if (!item.dose && row.dose) item.dose = row.dose;
                if (!item.frequency && row.frequency) item.frequency = row.frequency;
                if (!item.duration && row.duration) item.duration = row.duration;
                if (!item.timing && row.timing) item.timing = row.timing;
                if (row.dose || row.frequency || row.duration || row.timing) {
                    item.prefilled = true;
                }
            },

            /**
             * A brand that ships in exactly one strength has no choice to make;
             * filling it is carrying the catalogue across, not a guess.
             */
            async fillOnlyStrength(item) {
                await this.ensureDoseOptions(item.medicine_name);
                this.applyBrandDefaults(item);
                const options = this.doseOptions[(item.medicine_name || '').trim().toUpperCase()] || [];
                if (!item.dose && options.length === 1) {
                    item.dose = options[0].value;
                    item.prefilled = true;
                }
            },

            async pickMedicine(index, row) {
                const item = this.items[index];
                item.medicine_name = row.brand_name || '';
                item.generic_name = row.generic_name || '';
                this.applyPrefill(item, row);
                item.saved_default = false;
                this.closeMedicineResults();

                // Have this brand's real strengths ready before the doctor
                // reaches the dose cell, so the chips are never briefly empty.
                // Also backfill freq/duration/timing from brand defaults when
                // the search row itself was a SKU with empty cells.
                await this.fillOnlyStrength(item);
            },

            /**
             * Teach the pad this doctor's own line for this drug.
             *
             * Writes to My medicines, the one sanctioned writer of a doctor's
             * shortlist — an explicit tap, visible and editable there, not the
             * app inferring habits from consultations.
             */
            saveAsDefault(index) {
                const item = this.items[index];
                if (!(item.medicine_name || '').trim()) return;
                $wire.saveMedicineDefault({
                    medicine_name: item.medicine_name,
                    generic_name: item.generic_name || null,
                    dose: item.dose || null,
                    frequency: item.frequency || null,
                    duration: item.duration || null,
                    timing: item.timing || null,
                });
                item.saved_default = true;
                item.prefilled = false;
            },

            async searchDiagnosis() {
                const q = (this.diagnosisQuery || '').trim();
                if (q.length < 3) {
                    this.diagnosisResults = [];
                    return;
                }
                try {
                    const res = await fetch(`${this.conditionSearchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const rows = (data.results || data || []).map((row) => ({
                        id: row.id,
                        name: row.name || row.label,
                        advice: row.advice || '',
                        tests: row.tests || '',
                    }));
                    rows.push({ id: this.freeDiagnosisPrefix + q, name: q, advice: '', tests: '' });
                    this.diagnosisResults = rows;
                } catch (e) {
                    this.diagnosisResults = [{ id: this.freeDiagnosisPrefix + q, name: q, advice: '', tests: '' }];
                }
            },

            /** The first word of the typing box is the drug; the rest is dosing. */
            shorthandName() {
                return ((this.shorthand || '').trim().split(/\s+/)[0] || '').trim();
            },

            closeShorthandResults() {
                this.shorthandResults = [];
                this.shorthandIndex = -1;
            },

            /**
             * The typing box is also a search box.
             *
             * Doctors typed a name here and got a bare row back, because the
             * box only ever parsed tokens. It now searches the same endpoint
             * the Brand cell uses, so the shortcut for fast typists carries the
             * same knowledge as the slow path.
             */
            async searchShorthand() {
                const q = this.shorthandName();
                if (q.length < 2) {
                    this.closeShorthandResults();
                    return;
                }
                try {
                    const res = await fetch(`${this.medicineSearchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        this.shorthandResults = (window.ChamberQOffline?.searchMedicines(q) || []).slice(0, 6);
                        this.shorthandIndex = -1;
                        return;
                    }
                    const data = await res.json();
                    this.shorthandResults = (data.results || []).slice(0, 6);
                    this.shorthandIndex = -1;
                    window.ChamberQOffline?.rememberSearchResults(data.results || []);
                } catch (e) {
                    this.shorthandResults = (window.ChamberQOffline?.searchMedicines(q) || []).slice(0, 6);
                    this.shorthandIndex = -1;
                }
            },

            moveShorthand(step) {
                if (!this.shorthandResults.length) return;
                const next = this.shorthandIndex + step;
                this.shorthandIndex = next < 0
                    ? this.shorthandResults.length - 1
                    : next % this.shorthandResults.length;
            },

            /**
             * Only an exact brand match prefills a typed name.
             *
             * Typing `nap` and pressing Enter must not silently become NAPA
             * EXTRA — a near miss on a prescription is a different drug. The
             * suggestion list is there for anything less than exact.
             */
            async catalogueMatch(name) {
                const needle = (name || '').trim().toLowerCase();
                if (needle.length < 2) return null;
                const exact = (rows) => (rows || []).find(
                    (row) => (row.brand_name || '').trim().toLowerCase() === needle
                ) || null;

                const known = exact(this.shorthandResults);
                if (known) return known;

                try {
                    const res = await fetch(`${this.medicineSearchUrl}?q=${encodeURIComponent(needle)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return exact(window.ChamberQOffline?.searchMedicines(needle) || []);
                    const data = await res.json();
                    window.ChamberQOffline?.rememberSearchResults(data.results || []);

                    return exact(data.results);
                } catch (e) {
                    return exact(window.ChamberQOffline?.searchMedicines(needle) || []);
                }
            },

            async commitShorthand(row = null) {
                const raw = (this.shorthand || '').trim();
                const chosen = row
                    || (this.shorthandIndex >= 0 ? this.shorthandResults[this.shorthandIndex] : null);
                if (!raw && !chosen) return;
                const parts = raw ? raw.split(/\s+/) : [];
                const typedName = parts.length ? parts.shift() : '';

                const item = this.emptyItem();
                item.medicine_name = (chosen?.brand_name || typedName).toUpperCase();

                while (parts.length) {
                    const token = parts.shift();
                    const lower = token.toLowerCase();
                    if (this.timingShorthand[lower]) {
                        item.timing = this.timingShorthand[lower];
                        continue;
                    }
                    if (/^\d+(\.\d+)?(mg|g|ml|mcg)?$/i.test(token) || /^\d+\s*mg(\/\d+\s*ml)?$/i.test(token)) {
                        item.dose = /[a-zA-Z]/.test(token) ? token : `${token} mg`;
                        continue;
                    }
                    if (/^\d+\+\d+\+\d+$/.test(token) || token.includes('½')) {
                        item.frequency = token;
                        continue;
                    }
                    if (/^\d+d$/i.test(token)) {
                        item.duration = `${parseInt(token, 10)} days`;
                        continue;
                    }
                    if (/^\d+m$/i.test(token)) {
                        item.duration = `${parseInt(token, 10)} month`;
                        continue;
                    }
                    if (/^cont/i.test(token)) {
                        item.duration = 'Continue';
                        continue;
                    }
                    if (!item.dose) {
                        item.dose = token;
                    } else if (!item.frequency) {
                        item.frequency = token;
                    } else if (!item.duration) {
                        item.duration = token;
                    }
                }

                // Tokens typed after the name are already on the item, so the
                // catalogue only fills what is still blank.
                const match = chosen || await this.catalogueMatch(typedName);
                if (match) {
                    this.applyPrefill(item, match);
                }

                // Reuse the waiting blank line rather than leaving an empty row
                // sitting above the medicine that was just typed.
                const blank = this.items.findIndex((line) => !(line.medicine_name || '').trim());
                if (blank === -1) {
                    this.items.push(item);
                } else {
                    this.items.splice(blank, 1, item);
                }

                this.shorthand = '';
                this.closeShorthandResults();

                // Read the row back out of the list: only the reactive copy
                // repaints the table when the strength lands.
                const at = blank === -1 ? this.items.length - 1 : blank;
                await this.fillOnlyStrength(this.items[at]);
            },

            safetyWarnings() {
                const warnings = [];
                const generics = {};
                const brands = {};
                const normalize = (value) => (value || '').trim().toLowerCase();

                this.items.forEach((item) => {
                    const brand = normalize(item.medicine_name);
                    const generic = normalize(item.generic_name);
                    if (brand) {
                        brands[brand] = brands[brand] || [];
                        brands[brand].push(item.medicine_name);
                    }
                    if (generic) {
                        generics[generic] = generics[generic] || [];
                        generics[generic].push(item.medicine_name || generic);
                    }
                });

                Object.entries(generics).forEach(([generic, names]) => {
                    const unique = [...new Set(names.map((name) => normalize(name)))];
                    if (unique.length >= 2) {
                        warnings.push(`Same generic on multiple lines: ${generic} (${names.join(', ')})`);
                    }
                });

                Object.entries(brands).forEach(([brand, names]) => {
                    if (names.length >= 2) {
                        warnings.push(`Duplicate brand: ${names[0]}`);
                    }
                });

                const allergyText = (this.patientAllergies || '').trim();
                if (allergyText) {
                    const tokens = allergyText.split(/[,;\n\/]+/).map((part) => normalize(part)).filter((token) => token.length >= 3);
                    this.items.forEach((item) => {
                        const brand = (item.medicine_name || '').trim();
                        const generic = (item.generic_name || '').trim();
                        if (!brand && !generic) {
                            return;
                        }
                        const haystack = normalize(`${brand} ${generic}`);
                        tokens.forEach((token) => {
                            if (haystack.includes(token) || new RegExp(`\\b${token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'u').test(haystack)) {
                                const label = brand || generic;
                                warnings.push(`Allergy note may match: ${token} ↔ ${label}`);
                            }
                        });
                    });
                }

                return [...new Set(warnings)];
            },

            payload() {
                return {
                    chief_complaint: this.formatComplaints(),
                    history: this.formatHistory(),
                    on_examination: this.onExamination,
                    clinical_notes: this.clinicalNotes,
                    diagnosis: this.diagnosis,
                    weight_kg: this.weightKg === '' ? null : this.weightKg,
                    bp_systolic: this.bpSystolic === '' ? null : this.bpSystolic,
                    bp_diastolic: this.bpDiastolic === '' ? null : this.bpDiastolic,
                    pulse_bpm: this.pulseBpm === '' ? null : this.pulseBpm,
                    spo2_percent: this.spo2Percent === '' ? null : this.spo2Percent,
                    temperature_f: this.temperatureF === '' ? null : this.temperatureF,
                    advice: this.advice,
                    tests_advised: this.formatInvestigations(),
                    reports_seen: this.reportsSeen,
                    report_photos: this.reportPhotos,
                    follow_up_relative: this.followUpRelative || null,
                    follow_up_date: this.followUpDate || null,
                    follow_up_note: this.followUpNote || null,
                    voice_path: this.voicePath || null,
                    voice_transcript: this.voiceTranscript || null,
                    prescription_items: this.items
                        .filter((row) => (row.medicine_name || '').trim() !== '')
                        .map((row) => ({
                            medicine_name: row.medicine_name,
                            generic_name: row.generic_name || null,
                            indication: row.indication || null,
                            dose: row.dose || null,
                            frequency: row.frequency || null,
                            duration: row.duration || null,
                            timing: row.timing || null,
                            instructions: row.instructions || null,
                        })),
                };
            },

            async save(options = {}) {
                // An explicit save supersedes a pending draft; letting the
                // timer fire afterwards would post the same payload twice.
                clearTimeout(this.draftTimer);

                const payload = this.payload();
                const signature = JSON.stringify(payload);
                const printLocal = () => {
                    window.ChamberQOffline?.printPad({
                        patient: { name: this.patientName, age: this.patientAge },
                        data: { ...payload, diagnosis_label: this.diagnosisLabel },
                        on_my_paper: this.onMyPaper,
                    });
                };
                const saveLocal = async () => {
                    if (!window.ChamberQOffline) return;
                    await window.ChamberQOffline.enqueue({
                        type: 'rx_save',
                        booking_id: this.bookingId,
                        data: payload,
                    });
                    if (options.print || options.preview) {
                        printLocal();
                    }
                };

                if (window.ChamberQOffline && !window.ChamberQOffline.isLikelyOnline()) {
                    await saveLocal();
                    return;
                }

                this.saveState = 'saving';

                try {
                    const url = await $wire.saveRxDesk(payload);
                    this.savedSignature = signature;
                    this.saveState = 'saved';
                    if (!url) {
                        return;
                    }
                    if (options.preview) {
                        $wire.mountAction('previewPrescription');
                        return;
                    }
                    if (options.print) {
                        window.open(url, '_blank', 'noopener');
                    }
                } catch (e) {
                    this.saveState = 'unsaved';
                    await saveLocal();
                }
            },
        }));
    </script>
    @endscript
@endonce
