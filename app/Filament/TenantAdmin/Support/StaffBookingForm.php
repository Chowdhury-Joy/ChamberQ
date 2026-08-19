<?php

namespace App\Filament\TenantAdmin\Support;

use App\Exceptions\BookingUnavailableException;
use App\Filament\TenantAdmin\Resources\Patients\Schemas\PatientForm;
use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use App\Services\CarePath;
use App\Services\PatientService;
use App\Support\StaffDeskScope;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Phone / call-centre booking and desk walk-ins: visit type, sitting, patient.
 */
final class StaffBookingForm
{
    public const TYPE_USUAL = 'usual';

    public const TYPE_FOLLOWUP = 'followup';

    public const TYPE_INTERVENTION = 'intervention';

    public const TYPE_LAB = 'lab';

    public const LAB_MSK = 'msk';

    public const LAB_COLLECTION = 'collection';

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        return [
            DatePicker::make('booking_date')
                ->label(__('Date'))
                ->helperText(__('Any open sitting day from today through the booking window. Same window as the public website.'))
                ->native(false)
                ->required()
                ->minDate(now()->startOfDay())
                ->maxDate(PlatformSetting::onlineBookingMaxDate())
                ->displayFormat('D, j M Y')
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('bookable', null);
                }),
            Select::make('visit_type')
                ->label(__('Visit type'))
                ->helperText(__('Usual is a new serial. Follow-up is a return. Intervention is a procedure. Lab is a scan or collection.'))
                ->options(fn (): array => self::visitTypeOptions())
                ->default(self::TYPE_USUAL)
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('bookable', null);
                    $set('lab_type', null);
                    $set('intervention_type', null);
                }),
            Select::make('intervention_type')
                ->label(__('Intervention type'))
                ->helperText(__('PRP, epidural, nerve block — whatever is on this clinic’s fee list.'))
                ->options(fn (): array => self::interventionTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->native(false)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('bookable', null)),
            Select::make('lab_type')
                ->label(__('Lab type'))
                ->options(fn (): array => self::labTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('bookable', null)),
            Select::make('bookable')
                ->label(__('Sitting'))
                ->helperText(function (Get $get): string {
                    return match ($get('visit_type')) {
                        self::TYPE_INTERVENTION => __('Intervention rooms on this date. The type (PRP, epidural, …) is the step above.'),
                        self::TYPE_LAB => __('The scan or collection window on this date.'),
                        self::TYPE_FOLLOWUP => __('Return visit — same rooms as Usual.'),
                        default => __('Visit sittings — same rooms a patient can pick on the website.'),
                    };
                })
                ->options(fn (Get $get): array => self::bookableOptions(
                    (string) ($get('booking_date') ?? ''),
                    (string) ($get('visit_type') ?? self::TYPE_USUAL),
                    $get('lab_type'),
                ))
                ->required()
                ->native(false)
                ->searchable()
                ->preload(),
            TextInput::make('patient_phone')
                ->label(__('Phone number'))
                ->extraInputAttributes([
                    'name' => 'patient_phone',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('tel')
                ->tel()
                ->required()
                ->live(debounce: 400)
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $set('patient_id', null);
                    $set('patient_name', '');
                })
                ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
                ->validationMessages([
                    'regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
                ]),
            Checkbox::make('different_whatsapp')
                ->label(__('Different WhatsApp'))
                ->live()
                ->default(false),
            TextInput::make('whatsapp_phone')
                ->label(__('WhatsApp'))
                ->extraInputAttributes([
                    'name' => 'whatsapp_phone',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('tel')
                ->tel()
                ->visible(fn (Get $get): bool => (bool) $get('different_whatsapp'))
                ->required(fn (Get $get): bool => (bool) $get('different_whatsapp'))
                ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
                ->validationMessages([
                    'regex' => __('Please enter a valid Bangladeshi WhatsApp number, for example 01712345678.'),
                ]),
            Select::make('patient_id')
                ->label(__('Who is this for?'))
                ->options(function (Get $get): array {
                    $phone = $get('patient_phone');

                    if (blank($phone)) {
                        return [];
                    }

                    $patients = app(PatientService::class)->patientsForPhone($phone);

                    if ($patients->isEmpty()) {
                        return [];
                    }

                    return $patients
                        ->mapWithKeys(fn (Patient $patient) => [$patient->id => $patient->pickerLabel()])
                        ->put('__new__', __('Someone new'))
                        ->all();
                })
                ->visible(function (Get $get): bool {
                    $phone = $get('patient_phone');

                    if (blank($phone)) {
                        return false;
                    }

                    return app(PatientService::class)->patientsForPhone($phone)->isNotEmpty();
                })
                ->native(false)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state && $state !== '__new__') {
                        $patient = Patient::find($state);
                        $set('patient_name', $patient?->name ?? '');
                    }
                }),
            TextInput::make('patient_name')
                ->label(__('Patient name'))
                ->extraInputAttributes([
                    'name' => 'patient_name',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('name')
                ->required(),
            PatientForm::yearOfBirthInput(),
            TextInput::make('nid')
                ->label(__('NID number (optional)'))
                ->helperText(__('From the national ID card — helps reconnect records if the phone number changes.'))
                ->maxLength(17)
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! \App\Support\BdNid::isValid((string) $value)) {
                        $fail(__('Please enter a valid NID (10 or 13 digits), or leave it blank.'));
                    }
                }),
            Checkbox::make('share_clinical_history')
                ->label(__('Share health records with other ChamberQ doctors'))
                ->helperText(__('Visit notes and prescriptions can help the next ChamberQ doctor treat this patient safely. Voice notes and photos stay in this clinic.'))
                ->default(true),
            Select::make('referring_doctor_id')
                ->label(__('Referred by (outside GP)'))
                ->options(fn (): array => ReferringDoctor::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                    ->all())
                ->placeholder(__('Walk-in / no referrer'))
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => tenant()?->hasReferrals() ?? false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function visitTypeOptions(): array
    {
        $options = [
            self::TYPE_USUAL => __('Usual'),
            self::TYPE_FOLLOWUP => __('Follow-up'),
        ];

        if (tenant()?->hasStations()) {
            $options[self::TYPE_INTERVENTION] = __('Intervention');
        }

        if (self::labTypeOptions() !== []) {
            $options[self::TYPE_LAB] = __('Lab');
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function interventionTypeOptions(): array
    {
        if (! tenant()?->hasStations()) {
            return [];
        }

        return \App\Models\FeeCatalogItem::interventionTypeOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function labTypeOptions(): array
    {
        $options = [];

        if (tenant()?->hasStations()) {
            $options[self::LAB_MSK] = __('MSK scan');
        }

        if (tenant()?->hasFeature('lab_tests')) {
            foreach (LabTest::active()->ordered()->get() as $test) {
                $options['test:'.$test->id] = $test->name;
            }

            if (LabCollectionSlot::query()->exists()) {
                $options[self::LAB_COLLECTION] = __('Lab collection');
            }
        }

        return $options;
    }

    /**
     * Sittings (and lab windows) that meet on this calendar date for the chosen visit type.
     *
     * @return array<string, string>
     */
    public static function bookableOptions(string $ymd, string $visitType = self::TYPE_USUAL, mixed $labType = null, bool $allowOverflow = false): array
    {
        if ($ymd === '') {
            return [];
        }

        try {
            $date = Carbon::parse($ymd);
        } catch (\Throwable) {
            return [];
        }

        $dow = $date->dayOfWeek;
        $options = [];
        $user = auth()->user();
        $bookingService = app(BookingService::class);
        $labType = is_string($labType) ? $labType : null;

        $includeSessions = ! in_array($visitType, [self::TYPE_LAB], true)
            || $labType === self::LAB_MSK;
        $includeLabSlots = $visitType === self::TYPE_LAB
            && $labType !== null
            && $labType !== self::LAB_MSK
            && tenant()?->hasFeature('lab_tests');

        if ($includeSessions) {
            $sessionsQuery = ScheduleSession::query()
                ->with(['doctor', 'chamber'])
                ->where('day_of_week', $dow);

            match ($visitType) {
                self::TYPE_INTERVENTION => $sessionsQuery->where('kind', ScheduleSession::KIND_INTERVENTION),
                self::TYPE_LAB => $sessionsQuery->where('kind', ScheduleSession::KIND_MSK),
                default => $sessionsQuery->publiclyBookable(),
            };

            if ($user instanceof \App\Models\User) {
                StaffDeskScope::constrainScheduleSessions($sessionsQuery, $user);
            }

            foreach ($sessionsQuery->get() as $session) {
                $availability = $bookingService->availabilityFor($session, $date->toDateString(), $allowOverflow);
                $kindSuffix = (tenant()?->hasStations() && filled($session->kind))
                    ? ' · '.($session->kindLabel() ?? $session->kind)
                    : '';
                $seats = $availability['blocked']
                    ? __('Closed')
                    : __(':remaining of :cap left', [
                        'remaining' => $availability['remaining'],
                        'cap' => $availability['cap'],
                    ]);

                $options['session:'.$session->id] = sprintf(
                    '%s — %s (%s, %s–%s)%s · %s',
                    $session->doctor?->name ?? __('Unknown doctor'),
                    $session->chamber?->name ?? __('Unknown chamber'),
                    $session->session_name,
                    Carbon::parse($session->start_time)->format('g:i A'),
                    Carbon::parse($session->end_time)->format('g:i A'),
                    $kindSuffix,
                    $seats,
                );
            }
        }

        if ($includeLabSlots) {
            $labQuery = LabCollectionSlot::query()->with('chamber')->where('day_of_week', $dow);
            if ($user instanceof \App\Models\User) {
                $chamberIds = StaffDeskScope::chamberIdsFor($user);
                if ($chamberIds !== null) {
                    $labQuery->whereIn('chamber_id', $chamberIds);
                }
            }

            foreach ($labQuery->get() as $slot) {
                $availability = $bookingService->availabilityFor($slot, $date->toDateString(), $allowOverflow);
                $seats = $availability['blocked']
                    ? __('Closed')
                    : __(':remaining of :cap left', [
                        'remaining' => $availability['remaining'],
                        'cap' => $availability['cap'],
                    ]);

                $options['lab:'.$slot->id] = sprintf(
                    '%s — %s (%s–%s) · %s',
                    __('Lab collection'),
                    $slot->chamber?->name ?? __('Main lab'),
                    Carbon::parse($slot->start_time)->format('g:i A'),
                    Carbon::parse($slot->end_time)->format('g:i A'),
                    $seats,
                );
            }
        }

        return $options;
    }

    /**
     * Walk-in at the door today: same visit types as Book serial, no date picker.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function walkInComponents(): array
    {
        $today = Carbon::today()->toDateString();

        return array_merge(
            self::visitTypeFields(),
            [
                Select::make('bookable')
                    ->label(__('Sitting'))
                    ->helperText(function (Get $get): string {
                        return match ($get('visit_type')) {
                            self::TYPE_INTERVENTION => __('Intervention rooms today. Extra stools are allowed.'),
                            self::TYPE_LAB => __('The scan or collection window today. Extra stools are allowed.'),
                            self::TYPE_FOLLOWUP => __('Return visit — same rooms as Usual. Extra stools are allowed.'),
                            default => __('Visit sittings today. Extra stools are allowed.'),
                        };
                    })
                    ->options(fn (Get $get): array => self::bookableOptions(
                        $today,
                        (string) ($get('visit_type') ?? self::TYPE_USUAL),
                        $get('lab_type'),
                        true,
                    ))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->preload(),
            ],
            self::patientFields(includeWhatsapp: false),
        );
    }

    /**
     * Walk-in onto the sitting already open on Live Queue.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function liveQueueWalkInComponents(?ScheduleSession $session): array
    {
        $options = self::visitTypeOptionsForSitting($session);
        $defaultType = array_key_first($options) ?? self::TYPE_USUAL;

        $fields = [
            Select::make('visit_type')
                ->label(__('Visit type'))
                ->helperText(__('Usual is a new serial. Follow-up is a return. Intervention is a procedure. Lab is a scan or collection.'))
                ->options($options)
                ->default($defaultType)
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('lab_type', null);
                    $set('intervention_type', null);
                }),
            Select::make('intervention_type')
                ->label(__('Intervention type'))
                ->helperText(__('PRP, epidural, nerve block — whatever is on this clinic’s fee list.'))
                ->options(fn (): array => self::interventionTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->native(false)
                ->searchable(),
        ];

        if ($session?->kind === ScheduleSession::KIND_MSK) {
            $fields[] = Hidden::make('lab_type')->default(self::LAB_MSK);
        } else {
            $fields[] = Select::make('lab_type')
                ->label(__('Lab type'))
                ->options(fn (): array => self::labTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->native(false)
                ->live();
        }

        return array_merge($fields, self::patientFields(includeWhatsapp: false));
    }

    /**
     * @return array<string, string>
     */
    public static function visitTypeOptionsForSitting(?ScheduleSession $session): array
    {
        if ($session === null) {
            return self::visitTypeOptions();
        }

        return match ($session->kind) {
            ScheduleSession::KIND_INTERVENTION => [self::TYPE_INTERVENTION => __('Intervention')],
            ScheduleSession::KIND_MSK => [self::TYPE_LAB => __('Lab')],
            default => [
                self::TYPE_USUAL => __('Usual'),
                self::TYPE_FOLLOWUP => __('Follow-up'),
            ],
        };
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function visitTypeFields(): array
    {
        return [
            Select::make('visit_type')
                ->label(__('Visit type'))
                ->helperText(__('Usual is a new serial. Follow-up is a return. Intervention is a procedure. Lab is a scan or collection.'))
                ->options(fn (): array => self::visitTypeOptions())
                ->default(self::TYPE_USUAL)
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('bookable', null);
                    $set('lab_type', null);
                    $set('intervention_type', null);
                }),
            Select::make('intervention_type')
                ->label(__('Intervention type'))
                ->helperText(__('PRP, epidural, nerve block — whatever is on this clinic’s fee list.'))
                ->options(fn (): array => self::interventionTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_INTERVENTION
                    && self::interventionTypeOptions() !== [])
                ->native(false)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('bookable', null)),
            Select::make('lab_type')
                ->label(__('Lab type'))
                ->options(fn (): array => self::labTypeOptions())
                ->visible(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->required(fn (Get $get): bool => $get('visit_type') === self::TYPE_LAB)
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('bookable', null)),
        ];
    }

    /**
     * Phone, name, optional WhatsApp, household, NID, share, referrer.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function patientFields(bool $includeWhatsapp): array
    {
        $fields = [];

        $phone = TextInput::make('patient_phone')
            ->label(__('Phone number'))
            ->extraInputAttributes([
                'name' => 'patient_phone',
                'form' => 'cq-no-native-form',
            ])
            ->autocomplete('tel')
            ->tel()
            ->required()
            ->live(debounce: 400)
            ->afterStateUpdated(function (?string $state, Set $set): void {
                $set('patient_id', null);
                $set('patient_name', '');
            })
            ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
            ->validationMessages([
                'regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
            ]);

        $fields[] = $phone;

        if ($includeWhatsapp) {
            $fields[] = Checkbox::make('different_whatsapp')
                ->label(__('Different WhatsApp'))
                ->live()
                ->default(false);
            $fields[] = TextInput::make('whatsapp_phone')
                ->label(__('WhatsApp'))
                ->extraInputAttributes([
                    'name' => 'whatsapp_phone',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('tel')
                ->tel()
                ->visible(fn (Get $get): bool => (bool) $get('different_whatsapp'))
                ->required(fn (Get $get): bool => (bool) $get('different_whatsapp'))
                ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
                ->validationMessages([
                    'regex' => __('Please enter a valid Bangladeshi WhatsApp number, for example 01712345678.'),
                ]);
        }

        return array_merge($fields, [
            Select::make('patient_id')
                ->label(__('Who is this for?'))
                ->options(function (Get $get): array {
                    $phone = $get('patient_phone');

                    if (blank($phone)) {
                        return [];
                    }

                    $patients = app(PatientService::class)->patientsForPhone($phone);

                    if ($patients->isEmpty()) {
                        return [];
                    }

                    return $patients
                        ->mapWithKeys(fn (Patient $patient) => [$patient->id => $patient->pickerLabel()])
                        ->put('__new__', __('Someone new'))
                        ->all();
                })
                ->visible(function (Get $get): bool {
                    $phone = $get('patient_phone');

                    if (blank($phone)) {
                        return false;
                    }

                    return app(PatientService::class)->patientsForPhone($phone)->isNotEmpty();
                })
                ->native(false)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state && $state !== '__new__') {
                        $patient = Patient::find($state);
                        $set('patient_name', $patient?->name ?? '');
                    }
                }),
            TextInput::make('patient_name')
                ->label(__('Patient name'))
                ->extraInputAttributes([
                    'name' => 'patient_name',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('name')
                ->required(),
            PatientForm::yearOfBirthInput(),
            TextInput::make('nid')
                ->label(__('NID number (optional)'))
                ->helperText(__('From the national ID card — helps reconnect records if the phone number changes.'))
                ->maxLength(17)
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! \App\Support\BdNid::isValid((string) $value)) {
                        $fail(__('Please enter a valid NID (10 or 13 digits), or leave it blank.'));
                    }
                }),
            Checkbox::make('share_clinical_history')
                ->label(__('Share health records with other ChamberQ doctors'))
                ->helperText(__('Visit notes and prescriptions can help the next ChamberQ doctor treat this patient safely. Voice notes and photos stay in this clinic.'))
                ->default(true),
            Select::make('referring_doctor_id')
                ->label(__('Referred by (outside GP)'))
                ->options(fn (): array => ReferringDoctor::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                    ->all())
                ->placeholder(__('Walk-in / no referrer'))
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => tenant()?->hasReferrals() ?? false),
        ]);
    }

    public static function bookableMatchesVisitType(
        ScheduleSession|LabCollectionSlot $bookable,
        string $visitType,
        ?string $labType,
    ): bool {
        if ($bookable instanceof LabCollectionSlot) {
            return $visitType === self::TYPE_LAB
                && $labType !== null
                && $labType !== self::LAB_MSK;
        }

        return match ($visitType) {
            self::TYPE_INTERVENTION => $bookable->kind === ScheduleSession::KIND_INTERVENTION,
            self::TYPE_LAB => $bookable->kind === ScheduleSession::KIND_MSK
                && $labType === self::LAB_MSK,
            default => $bookable->isPubliclyBookable(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createFromState(
        array $data,
        string $ymd,
        bool $allowOverflow,
        bool $allowEndedToday,
        bool $sendSms,
        ScheduleSession|LabCollectionSlot|null $forcedBookable = null,
    ): Booking {
        if ($forcedBookable) {
            $bookable = $forcedBookable;
        } else {
            [$type, $id] = explode(':', (string) ($data['bookable'] ?? ''), 2);
            $bookable = $type === 'lab'
                ? LabCollectionSlot::findOrFail($id)
                : ScheduleSession::findOrFail($id);
        }

        $visitType = (string) ($data['visit_type'] ?? self::TYPE_USUAL);
        $labType = is_string($data['lab_type'] ?? null) ? $data['lab_type'] : null;
        if ($bookable instanceof ScheduleSession && $bookable->kind === ScheduleSession::KIND_MSK && $labType === null) {
            $labType = self::LAB_MSK;
            $visitType = self::TYPE_LAB;
        }

        $interventionTypeId = filled($data['intervention_type'] ?? null)
            ? (int) $data['intervention_type']
            : null;

        if ($visitType === self::TYPE_INTERVENTION
            && self::interventionTypeOptions() !== []
            && $interventionTypeId === null) {
            throw BookingUnavailableException::pickInterventionType();
        }

        if (! self::bookableMatchesVisitType($bookable, $visitType, $labType)) {
            throw BookingUnavailableException::visitTypeMismatch();
        }

        $patientId = ($data['patient_id'] ?? null) === '__new__'
            ? null
            : ($data['patient_id'] ?? null);

        $labTestIds = [];
        if (is_string($labType) && str_starts_with($labType, 'test:')) {
            $labTestIds[] = (int) substr($labType, 5);
        }

        $forcedCarePath = match ($visitType) {
            self::TYPE_FOLLOWUP => CarePath::FOLLOW_UP,
            self::TYPE_USUAL => CarePath::VISIT,
            default => null,
        };

        return app(BookingService::class)->createBookingForBookable(
            $bookable,
            $ymd,
            $data['patient_name'],
            $data['patient_phone'],
            $labTestIds,
            sendSms: $sendSms,
            patientId: $patientId,
            wantsEarlierDate: false,
            whatsappPhone: ! empty($data['different_whatsapp']) && filled($data['whatsapp_phone'] ?? null)
                ? (string) $data['whatsapp_phone']
                : null,
            shareClinicalHistory: array_key_exists('share_clinical_history', $data)
                ? (bool) $data['share_clinical_history']
                : true,
            nid: $data['nid'] ?? null,
            yearOfBirth: filled($data['year_of_birth'] ?? null) ? (int) $data['year_of_birth'] : null,
            allowOverflow: $allowOverflow,
            allowEndedToday: $allowEndedToday,
            seenBeforeSoftware: $visitType === self::TYPE_FOLLOWUP ? true : null,
            allowMskWalkIn: $bookable instanceof ScheduleSession
                && $bookable->kind === ScheduleSession::KIND_MSK,
            referringDoctorId: filled($data['referring_doctor_id'] ?? null)
                ? (int) $data['referring_doctor_id']
                : null,
            forcedCarePath: $forcedCarePath,
            feeCatalogItemId: $visitType === self::TYPE_INTERVENTION
                ? $interventionTypeId
                : null,
        );
    }
}
