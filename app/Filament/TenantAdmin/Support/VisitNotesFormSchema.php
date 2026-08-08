<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\VisitRecord;
use App\Services\ConditionService;
use App\Services\MedicineService;
use App\Services\VisitMediaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Schemas\Components\View;
use Illuminate\Support\Js;

class VisitNotesFormSchema
{
    private const VISIT_NOTES_HINT = 'All fields are optional — leave blank to complete without notes.';

    /** Browser event announcing that "Add medicine" has appended a row. */
    public const MEDICINE_ADDED_EVENT = 'prescription-medicine-added';

    private const STAFF_ENTRY_HINT = 'Type only what the doctor wrote on paper. Attach a photo of the slip so it can be checked later.';

    /**
     * Fields staff may write when typing up a paper prescription.
     *
     * Enforced server-side as well as hidden from the form, so a hand-crafted
     * request cannot slip a diagnosis or voice note past the role split.
     *
     * @var list<string>
     */
    public const STAFF_WRITABLE_FIELDS = [
        'prescription_items',
        'follow_up_relative',
        'follow_up_date',
        'follow_up_note',
        'prescription_photo',
    ];

    /**
     * Sticky header + footer so Save / Complete stays visible on long phone modals.
     */
    public static function configureModal(Action $action): Action
    {
        return $action
            ->stickyModalHeader()
            ->stickyModalFooter();
    }

    public const FREE_DIAGNOSIS_PREFIX = '__free__:';

    /**
     * Plausible-reading bounds for the optional vitals boxes. These are not
     * clinical limits — they only catch a slipped digit (a 1700 systolic, a
     * weight typed into the BP box), which is why they are generous.
     */
    public const WEIGHT_MIN_KG = 0.5;

    public const WEIGHT_MAX_KG = 300;

    public const BP_SYSTOLIC_MIN = 60;

    public const BP_SYSTOLIC_MAX = 250;

    public const BP_DIASTOLIC_MIN = 30;

    public const BP_DIASTOLIC_MAX = 150;

    /** @var list<string> */
    public const FREQUENCY_PRESETS = ['1+0+1', '1+1+1', '0+0+1', '1+0+0', '½+0+½'];

    /** @var list<string> */
    public const DURATION_PRESETS = ['3 days', '5 days', '7 days', '10 days', '14 days', '1 month', 'Continue'];

    /** @var list<string> */
    public const DOSE_PRESETS = ['500 mg', '10 mg', '20 mg', '40 mg', '400 mg', '5 mg', '50 mg'];

    /**
     * @return array<string, mixed>
     */
    public static function stateFromRecord(?VisitRecord $record): array
    {
        if (! $record) {
            return ['_visit_notes_hint' => __(self::VISIT_NOTES_HINT)];
        }

        $diagnosis = $record->condition_id
            ?? (filled($record->diagnosis_uncoded)
                ? self::FREE_DIAGNOSIS_PREFIX.$record->diagnosis_uncoded
                : null);

        return [
            '_visit_notes_hint' => __(self::VISIT_NOTES_HINT),
            'diagnosis' => $diagnosis,
            'condition_id' => $record->condition_id,
            'diagnosis_free_text' => $record->diagnosis_uncoded,
            'weight_kg' => $record->weight_kg,
            'bp_systolic' => $record->bp_systolic,
            'bp_diastolic' => $record->bp_diastolic,
            'clinical_notes' => $record->clinical_notes,
            'advice' => $record->advice,
            'tests_advised' => $record->tests_advised,
            'reports_seen' => $record->reports_seen,
            'follow_up_date' => $record->follow_up_date?->toDateString(),
            'follow_up_note' => $record->follow_up_note,
            'follow_up_relative' => self::inferRelativeFollowUp($record->follow_up_date),
            'voice_path' => $record->voice_path,
            'voice_transcript' => $record->voice_transcript,
            'prescription_photo' => filled($record->photo_path) ? [$record->photo_path] : [],
            'prescription_items' => $record->prescription
                ? $record->prescription->items
                    ->map(fn (\App\Models\PrescriptionItem $item): array => self::prescriptionItemStateFromStored(
                        $item->medicine_name,
                        $item->generic_name,
                        $item->dose,
                        $item->frequency,
                        $item->duration,
                    ))
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeSubmission(array $data): array
    {
        if (blank($data['diagnosis'] ?? null) && filled($data['diagnosis_free_text'] ?? null)) {
            $data['diagnosis'] = self::FREE_DIAGNOSIS_PREFIX.$data['diagnosis_free_text'];
        }

        $diagnosis = $data['diagnosis'] ?? null;

        if (filled($diagnosis)) {
            if (str_starts_with((string) $diagnosis, self::FREE_DIAGNOSIS_PREFIX)) {
                $data['condition_id'] = null;
                $data['diagnosis_free_text'] = substr((string) $diagnosis, strlen(self::FREE_DIAGNOSIS_PREFIX));
            } else {
                $data['condition_id'] = $diagnosis;
                $data['diagnosis_free_text'] = null;
            }
        }

        if (($data['follow_up_relative'] ?? null) === 'as_needed') {
            $data['follow_up_date'] = null;
            $data['follow_up_note'] = $data['follow_up_note'] ?? __('Come back if it does not improve');
        } elseif (filled($data['follow_up_relative'] ?? null) && blank($data['follow_up_date'] ?? null)) {
            $data['follow_up_date'] = self::dateFromRelative((string) $data['follow_up_relative']);
            $data['follow_up_note'] = null;
        }

        $items = collect($data['prescription_items'] ?? [])
            ->map(function (array $item): array {
                $medicineService = app(MedicineService::class);

                $frequency = $item['frequency'] ?? null;
                if ($frequency === 'other') {
                    $frequency = $item['frequency_other'] ?? null;
                }

                $duration = $item['duration'] ?? null;
                if ($duration === 'other') {
                    $duration = $item['duration_other'] ?? null;
                }

                $dose = $item['dose'] ?? null;
                if ($dose === 'other') {
                    $dose = $item['dose_other'] ?? null;
                }

                return [
                    'medicine_name' => $medicineService->resolveMedicineNameFromFormState($item),
                    'generic_name' => $item['generic_name'] ?? null,
                    'dose' => $dose,
                    'frequency' => $frequency,
                    'duration' => $duration,
                ];
            })
            ->all();

        $data['prescription_items'] = $items;
        $data = self::normalizeVitals($data);

        return $data;
    }

    /**
     * Cast optional visit vitals, dropping anything that is not a usable
     * reading. This **must not throw**: it runs inside `submissionHasContent()`,
     * which every completion path calls *before* closing the booking and
     * advancing the queue, so a rejected value here would strand the patient in
     * the chamber. Notes have never been allowed to block queue throughput
     * (see the Stage 4 decision in `decisions.md`), and a typo in a vitals box
     * is not the moment to start.
     *
     * The doctor is stopped earlier instead — `vitalsSection()` carries the
     * same rules as Filament field rules, so the UI shows the error next to the
     * input and a bad reading never reaches here. What arrives here is either
     * already valid or hand-crafted, and half a blood pressure is not a
     * clinical fact worth storing, so it is dropped as a pair.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeVitals(array $data): array
    {
        $weight = $data['weight_kg'] ?? null;
        $data['weight_kg'] = is_numeric($weight)
            && (float) $weight >= self::WEIGHT_MIN_KG
            && (float) $weight <= self::WEIGHT_MAX_KG
                ? round((float) $weight, 1)
                : null;

        $systolic = is_numeric($data['bp_systolic'] ?? null) ? (int) $data['bp_systolic'] : null;
        $diastolic = is_numeric($data['bp_diastolic'] ?? null) ? (int) $data['bp_diastolic'] : null;

        if (! self::isUsableBloodPressure($systolic, $diastolic)) {
            $systolic = null;
            $diastolic = null;
        }

        $data['bp_systolic'] = $systolic;
        $data['bp_diastolic'] = $diastolic;

        $notes = trim((string) ($data['clinical_notes'] ?? ''));
        $data['clinical_notes'] = $notes === '' ? null : $notes;

        return $data;
    }

    /**
     * A blood pressure is only a fact as a pair, in range, systolic over
     * diastolic. Shared by the form rules and by `normalizeVitals()` so the
     * screen and the database can never disagree about what is acceptable.
     */
    public static function isUsableBloodPressure(?int $systolic, ?int $diastolic): bool
    {
        if ($systolic === null || $diastolic === null) {
            return false;
        }

        return $systolic >= self::BP_SYSTOLIC_MIN
            && $systolic <= self::BP_SYSTOLIC_MAX
            && $diastolic >= self::BP_DIASTOLIC_MIN
            && $diastolic <= self::BP_DIASTOLIC_MAX
            && $systolic > $diastolic;
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(
        ?Patient $patient = null,
        ?VisitRecord $lastVisitRecord = null,
        ?Booking $booking = null,
    ): array {
        $medicineService = app(MedicineService::class);
        $prescribingDoctor = $medicineService->resolvePrescribingDoctor($booking);

        $lastItems = $lastVisitRecord?->prescription?->items
            ? $lastVisitRecord->prescription->items
                ->map(fn (\App\Models\PrescriptionItem $item): array => self::prescriptionItemStateFromStored(
                    $item->medicine_name,
                    $item->generic_name,
                    $item->dose,
                    $item->frequency,
                    $item->duration,
                ))
                ->values()
                ->all()
            : [];

        return [
            TextInput::make('_visit_notes_hint')
                ->hiddenLabel()
                ->default(__(self::VISIT_NOTES_HINT))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
            View::make('filament.tenant-admin.components.visit-notes-allergy-strip')
                ->viewData(['patient' => $patient])
                ->columnSpanFull()
                ->visible(fn (): bool => $patient?->hasClinicalWarnings() ?? false),
            self::prescriptionSection($prescribingDoctor, $lastItems),
            self::vitalsSection(),
            Section::make(__('Diagnosis'))
                ->schema([
                    Select::make('diagnosis')
                        ->label(__('Diagnosis'))
                        ->placeholder(__('Type at least 3 letters to search…'))
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(function (string $search): array {
                            if (mb_strlen(trim($search)) < ConditionService::MIN_SEARCH_LENGTH) {
                                return [];
                            }

                            $options = app(ConditionService::class)
                                ->search($search, auth()->user())
                                ->mapWithKeys(fn (array $row) => [$row['id'] => $row['name']])
                                ->all();

                            $trimmed = trim($search);
                            if (mb_strlen($trimmed) >= ConditionService::MIN_SEARCH_LENGTH) {
                                $options[self::FREE_DIAGNOSIS_PREFIX.$trimmed] = __('Use what I typed: :text', ['text' => $trimmed]);
                            }

                            return $options;
                        })
                        ->getOptionLabelUsing(function (?string $value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            if (str_starts_with($value, self::FREE_DIAGNOSIS_PREFIX)) {
                                return substr($value, strlen(self::FREE_DIAGNOSIS_PREFIX));
                            }

                            return Condition::query()->find($value)?->name ?? $value;
                        })
                        ->native(false),
                ])
                ->columns(1),
            Textarea::make('clinical_notes')
                ->label(__('Clinical notes'))
                ->helperText(__('Chief complaint, exam findings, measurements — whatever you would write on the left of a paper pad.'))
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('advice')
                ->label(__('Advice'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('tests_advised')
                ->label(__('Tests advised'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('reports_seen')
                ->label(__('Reports the patient brought'))
                ->helperText(__('Blood tests, X-rays, or other reports you looked at today.'))
                ->rows(2)
                ->columnSpanFull(),
            self::followUpSection(),
            Section::make(__('Voice note'))
                ->schema([
                    View::make('filament.tenant-admin.components.visit-voice-recorder')
                        ->columnSpanFull(),
                    Hidden::make('voice_path')
                        ->extraAttributes(['data-visit-voice-path' => 'true']),
                    Textarea::make('voice_transcript')
                        ->label(__('Voice note summary'))
                        ->helperText(__('Optional. Type a short summary of what you said, so the note is searchable later.'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            self::paperPhotoSection(),
        ];
    }

    /**
     * Staff typing up a prescription the doctor wrote on paper.
     *
     * Medicines, follow-up and the photo of the slip only — no diagnosis,
     * vitals, clinical notes, advice, tests, voice note or allergy history.
     * Staff are transcribing, not making a clinical judgement, so widening
     * this would hand them patient history the role split says they may not read.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function staffPrescriptionComponents(?Booking $booking = null): array
    {
        $prescribingDoctor = app(MedicineService::class)->resolvePrescribingDoctor($booking);

        return [
            TextInput::make('_staff_entry_hint')
                ->hiddenLabel()
                ->default(__(self::STAFF_ENTRY_HINT))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
            self::prescriptionSection($prescribingDoctor),
            self::followUpSection(),
            self::paperPhotoSection(),
        ];
    }

    /**
     * Form state for the staff entry modal — prescription fields only, so
     * reopening never surfaces the doctor's diagnosis or voice note.
     *
     * @return array<string, mixed>
     */
    public static function staffStateFromRecord(?VisitRecord $record): array
    {
        $base = ['_staff_entry_hint' => __(self::STAFF_ENTRY_HINT)];

        if (! $record) {
            return $base + ['prescription_items' => []];
        }

        $full = self::stateFromRecord($record);

        return $base + [
            'prescription_items' => $full['prescription_items'],
            'follow_up_date' => $full['follow_up_date'],
            'follow_up_note' => $full['follow_up_note'],
            'follow_up_relative' => $full['follow_up_relative'],
            'prescription_photo' => $full['prescription_photo'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lastItems
     */
    private static function prescriptionSection(?Doctor $prescribingDoctor, array $lastItems = []): Section
    {
        return Section::make(__('Prescription'))
            ->schema([
                View::make('filament.tenant-admin.components.copy-last-prescription')
                    ->viewData(['items' => $lastItems])
                    ->columnSpanFull()
                    ->visible(fn (): bool => $lastItems !== []),
                Repeater::make('prescription_items')
                    ->label(__('Medicines'))
                    ->schema(self::prescriptionItemSchema($prescribingDoctor))
                    ->columns(1)
                    ->defaultItems(0)
                    ->addActionLabel(__('Add medicine'))
                    ->reorderable()
                    ->collapsible()
                    ->collapsed(fn ($item): bool => filled($item?->getRawState()['medicine_name'] ?? null))
                    ->itemLabel(fn (array $state): ?string => filled($state['medicine_name'] ?? null)
                        ? mb_strtoupper((string) $state['medicine_name'])
                        : null)
                    // `collapsed()` only decides an item's *initial* Alpine state. Livewire's
                    // morph keeps already-mounted items' client-side state across a re-render,
                    // so a row filled in earlier never re-collapses on its own when the next
                    // one is added — the doctor ends up scrolling past finished medicines.
                    // Dispatching `repeater-collapse` is what Filament's own "Collapse all"
                    // does, and it reaches mounted rows; the appended row is a brand-new node
                    // that mounts expanded, so it is still the one ready to type into.
                    // The key must be `x-on:click.capture`, not `x-on:click`: Action seeds a
                    // plain `x-on:click` into its attribute bag, and the bag beats merged extra
                    // attributes, so that spelling is silently dropped. `alpineClickHandler()`
                    // is worse — it *replaces* the action's `wire:click`, leaving a button that
                    // collapses the list but never adds a row.
                    ->addAction(fn (Action $action) => $action
                        ->extraAttributes(
                            fn (Repeater $component): array => [
                                'x-on:click.capture' => '$dispatch('.Js::from('repeater-collapse').', '.Js::from($component->getStatePath()).')',
                            ],
                        )
                        // Fired once the new row exists in the DOM. Collapsing the rows above
                        // shrinks the page under the doctor's scroll position, so without this
                        // the empty row lands off-screen and they have to scroll back up to it.
                        ->after(fn (\Livewire\Component $livewire) => $livewire->dispatch(self::MEDICINE_ADDED_EVENT)))
                    ->extraAttributes([
                        // Scopes the "already written up" row styling in theme.css to this
                        // repeater — the page builder has a dozen unrelated ones.
                        'class' => 'cs-rx-medicines',
                        'x-on:'.self::MEDICINE_ADDED_EVENT.'.window' => '$nextTick(() => { const rows = $el.querySelectorAll(\'.fi-fo-repeater-item\'); rows[rows.length - 1]?.scrollIntoView({ block: \'center\' }); })',
                    ]),
            ])
            ->columnSpanFull();
    }

    /**
     * Optional weight and blood pressure.
     *
     * The rules live here, on the fields, rather than only in the save path:
     * a doctor who mistypes must see the message next to the box they typed
     * in, and the save path must never refuse a submission (see
     * `normalizeVitals()` — it runs before the queue advances). Blood pressure
     * is required as a pair in both directions, so filling one box and tapping
     * Complete is caught on screen instead of silently dropping the reading.
     */
    private static function vitalsSection(): Section
    {
        $outOfRange = __('Blood pressure looks out of range. Check the reading.');
        $bothOrNeither = __('Enter both systolic and diastolic blood pressure, or leave both blank.');

        return Section::make(__('Vitals'))
            ->description(__('Optional. Leave blank if you did not measure.'))
            ->schema([
                TextInput::make('weight_kg')
                    ->label(__('Weight (kg)'))
                    ->numeric()
                    ->minValue(self::WEIGHT_MIN_KG)
                    ->maxValue(self::WEIGHT_MAX_KG)
                    ->step(0.1)
                    ->inputMode('decimal')
                    ->placeholder('58.5')
                    ->validationMessages([
                        'numeric' => __('Weight must be between 0.5 and 300 kg.'),
                        'min' => __('Weight must be between 0.5 and 300 kg.'),
                        'max' => __('Weight must be between 0.5 and 300 kg.'),
                    ]),
                TextInput::make('bp_systolic')
                    ->label(__('BP systolic'))
                    ->numeric()
                    ->minValue(self::BP_SYSTOLIC_MIN)
                    ->maxValue(self::BP_SYSTOLIC_MAX)
                    ->inputMode('numeric')
                    ->placeholder('170')
                    ->requiredWith('bp_diastolic')
                    ->rule(static fn (Get $get) => static function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $diastolic = $get('bp_diastolic');

                        if (blank($value) || blank($diastolic) || ! is_numeric($value) || ! is_numeric($diastolic)) {
                            return;
                        }

                        if ((int) $value <= (int) $diastolic) {
                            $fail(__('Systolic pressure must be higher than diastolic.'));
                        }
                    })
                    ->validationMessages([
                        'numeric' => __('Blood pressure must be a number.'),
                        'min' => $outOfRange,
                        'max' => $outOfRange,
                        'required_with' => $bothOrNeither,
                    ]),
                TextInput::make('bp_diastolic')
                    ->label(__('BP diastolic'))
                    ->numeric()
                    ->minValue(self::BP_DIASTOLIC_MIN)
                    ->maxValue(self::BP_DIASTOLIC_MAX)
                    ->inputMode('numeric')
                    ->placeholder('100')
                    ->requiredWith('bp_systolic')
                    ->validationMessages([
                        'numeric' => __('Blood pressure must be a number.'),
                        'min' => $outOfRange,
                        'max' => $outOfRange,
                        'required_with' => $bothOrNeither,
                    ]),
            ])
            ->columns(3);
    }

    private static function followUpSection(): Section
    {
        return Section::make(__('Follow-up'))
            ->schema([
                ToggleButtons::make('follow_up_relative')
                    ->label(__('Come back'))
                    ->options([
                        '1_week' => __('1 week'),
                        '2_weeks' => __('2 weeks'),
                        '1_month' => __('1 month'),
                        '3_months' => __('3 months'),
                        'as_needed' => __('As needed'),
                        'pick_date' => __('Pick a date'),
                    ])
                    ->inline()
                    ->live()
                    ->columnSpanFull(),
                DatePicker::make('follow_up_date')
                    ->label(__('Or pick a date'))
                    ->native(false)
                    ->minDate(now()->toDateString())
                    ->visible(fn (Get $get): bool => $get('follow_up_relative') === 'pick_date')
                    ->columnSpanFull(),
                TextInput::make('follow_up_note')
                    ->label(__('Follow-up note'))
                    ->placeholder(__('e.g. Come back if fever continues'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('follow_up_relative') === 'as_needed')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    private static function paperPhotoSection(): Section
    {
        return Section::make(__('Paper prescription photo'))
            ->schema([
                FileUpload::make('prescription_photo')
                    ->label(__('Photo of handwritten prescription'))
                    ->helperText(__('Take a photo on your phone or upload a scan. No handwriting recognition.'))
                    ->image()
                    ->acceptedFileTypes(VisitMediaService::allowedPhotoMimeTypes())
                    ->maxSize(5120)
                    ->disk('local')
                    ->directory(fn () => app(VisitMediaService::class)->photoDirectory())
                    ->visibility('private')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Read-only summary for Complete visit when notes already exist.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function summaryComponents(VisitRecord $record): array
    {
        return [
            View::make('filament.tenant-admin.components.visit-notes-summary')
                ->viewData(['record' => $record])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function prescriptionItemSchema(?Doctor $prescribingDoctor = null): array
    {
        return [
            MedicinePickerFields::prescriptionMedicineSelect($prescribingDoctor),
            View::make('filament.tenant-admin.components.medicine-prefill-hint')
                ->visible(fn (Get $get): bool => (bool) $get('_prefilled'))
                ->columnSpanFull(),
            Hidden::make('_prefilled')->default(false)->dehydrated(false),
            Hidden::make('_uncertain')->default(false)->dehydrated(false),
            TextInput::make('generic_name')
                ->label(__('Generic name'))
                ->placeholder(__('e.g. Paracetamol'))
                ->maxLength(120),
            ToggleButtons::make('dose')
                ->label(__('Dose'))
                ->options(array_merge(
                    array_combine(self::DOSE_PRESETS, self::DOSE_PRESETS),
                    ['other' => __('Other')]
                ))
                ->inline()
                ->live(),
            TextInput::make('dose_other')
                ->label(__('Dose (other)'))
                ->placeholder(__('e.g. 625 mg'))
                ->maxLength(80)
                ->visible(fn (Get $get): bool => $get('dose') === 'other'),
            ToggleButtons::make('frequency')
                ->label(__('Frequency'))
                ->options(array_merge(
                    array_combine(self::FREQUENCY_PRESETS, self::FREQUENCY_PRESETS),
                    ['other' => __('Other')]
                ))
                ->inline()
                ->live(),
            TextInput::make('frequency_other')
                ->label(__('Frequency (other)'))
                ->maxLength(80)
                ->visible(fn (Get $get): bool => $get('frequency') === 'other'),
            ToggleButtons::make('duration')
                ->label(__('Duration'))
                ->options(array_merge(
                    array_combine(self::DURATION_PRESETS, self::DURATION_PRESETS),
                    ['other' => __('Other')]
                ))
                ->inline()
                ->live(),
            TextInput::make('duration_other')
                ->label(__('Duration (other)'))
                ->maxLength(80)
                ->visible(fn (Get $get): bool => $get('duration') === 'other'),
        ];
    }

    private static function inferRelativeFollowUp(?\Carbon\CarbonInterface $date): ?string
    {
        if (! $date) {
            return null;
        }

        $diff = (int) now()->startOfDay()->diffInDays($date->startOfDay(), false);

        return match ($diff) {
            7 => '1_week',
            14 => '2_weeks',
            30, 31 => '1_month',
            90, 91, 92 => '3_months',
            default => 'pick_date',
        };
    }

    public static function dateFromRelative(string $relative): ?string
    {
        return match ($relative) {
            '1_week' => now()->addWeek()->toDateString(),
            '2_weeks' => now()->addWeeks(2)->toDateString(),
            '1_month' => now()->addMonth()->toDateString(),
            '3_months' => now()->addMonths(3)->toDateString(),
            default => null,
        };
    }

    public static function followUpDisplayLabel(?\Carbon\CarbonInterface $date, ?string $note = null): ?string
    {
        if (filled($note)) {
            return $note;
        }

        if (! $date) {
            return null;
        }

        $relativePhrase = self::relativeFollowUpPhrase($date);

        if ($relativePhrase) {
            return __('Come back in about :relative (:date)', [
                'relative' => $relativePhrase,
                'date' => $date->translatedFormat('j F Y'),
            ]);
        }

        return $date->translatedFormat('j F Y');
    }

    public static function relativeFollowUpPhrase(?\Carbon\CarbonInterface $date): ?string
    {
        return match (self::inferRelativeFollowUp($date)) {
            '1_week' => __('1 week'),
            '2_weeks' => __('2 weeks'),
            '1_month' => __('1 month'),
            '3_months' => __('3 months'),
            default => null,
        };
    }

    private static function isFrequencyPreset(?string $value): bool
    {
        return filled($value) && in_array($value, self::FREQUENCY_PRESETS, true);
    }

    private static function isDurationPreset(?string $value): bool
    {
        return filled($value) && in_array($value, self::DURATION_PRESETS, true);
    }

    private static function isDosePreset(?string $value): bool
    {
        return filled($value) && in_array($value, self::DOSE_PRESETS, true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function prescriptionItemStateFromStored(
        ?string $medicineName,
        ?string $genericName,
        ?string $dose,
        ?string $frequency,
        ?string $duration,
    ): array {
        $brand = filled($medicineName) ? mb_strtoupper(trim($medicineName)) : null;

        return [
            'medicine_name' => $brand,
            'generic_name' => $genericName,
            'dose' => self::isDosePreset($dose) ? $dose : (filled($dose) ? 'other' : null),
            'dose_other' => self::isDosePreset($dose) ? null : $dose,
            'frequency' => self::isFrequencyPreset($frequency) ? $frequency : (filled($frequency) ? 'other' : null),
            'frequency_other' => self::isFrequencyPreset($frequency) ? null : $frequency,
            'duration' => self::isDurationPreset($duration) ? $duration : (filled($duration) ? 'other' : null),
            'duration_other' => self::isDurationPreset($duration) ? null : $duration,
            '_prefilled' => false,
        ];
    }
}
