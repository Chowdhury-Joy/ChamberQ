<?php

namespace App\Filament\TenantAdmin\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use App\Filament\TenantAdmin\Resources\Patients\Schemas\PatientForm;
use App\Filament\TenantAdmin\Support\RosterRecordActions;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use Carbon\Carbon;
use App\Exceptions\BookingUnavailableException;
use App\Services\BookingService;
use App\Services\LiveSessionService;
use App\Services\PatientService;
use App\Services\SittingPrompt;
use Filament\Notifications\Notification;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class DailyRoster extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Daily Roster';

    protected string $view = 'filament.tenant-admin.pages.daily-roster';

    /**
     * Waiting bookings after the last "Mark Late", for optional WhatsApp
     * hand-off when that doctor's doctor_late WhatsApp preference is on.
     *
     * @var list<int|string>
     */
    public array $delayedNotifyBookingIds = [];

    /** Minutes from the last Mark Late, used in WhatsApp copy. */
    public int $delayedNotifyMinutes = 0;

    public function getSittingPromptsProperty(): \Illuminate\Support\Collection
    {
        return app(SittingPrompt::class)->promptsForToday();
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (($user?->isAdmin() ?? false) || ($user?->canWorkDesk() ?? false))
            && (tenant()?->hasFrontDoor() ?? false);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $bookingQuery = Booking::query()
            ->where('booking_date', today()->toDateString())
            ->with(['visitRecord.prescription', 'cashEntry.feeCatalogItem', 'bookable.doctor', 'bookable', 'labTests', 'procedureBookings.bookable', 'patient'])
            ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
            ->orderBy('serial_number');

        if ($user instanceof \App\Models\User) {
            StaffDeskScope::constrainBookings($bookingQuery, $user);
        }

        return $table
            ->query($bookingQuery)
            ->columns([
                TextColumn::make('serial_number')->label(__('Serial')),
                TextColumn::make('voucher_number')
                    ->label(__('Voucher'))
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('bookable.kind')
                    ->label(__('Room'))
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false)
                    ->formatStateUsing(function (?string $state, Booking $record): string {
                        if ($record->bookable_type !== ScheduleSession::class) {
                            return '—';
                        }

                        return $record->bookable?->kindLabel() ?? '—';
                    }),
                TextColumn::make('procedure_status')
                    ->label(__('Procedure'))
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false)
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (Booking::procedureStatusOptions()[$state] ?? $state)
                        : '—')
                    ->badge()
                    ->color('info'),
                TextColumn::make('patient_name')->label(__('Name'))->searchable(),
                TextColumn::make('patient_phone')->label(__('Phone'))->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'waiting' => __('Waiting'),
                        'called' => __('Called'),
                        'in_chamber' => __('In chamber'),
                        'completed' => __('Completed'),
                        'cancelled' => __('Cancelled'),
                        'no_show' => __('No-show'),
                        'skipped' => __('Skipped'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('cashEntry.amount')
                    ->label(__('Fee'))
                    ->formatStateUsing(function (?int $state, Booking $record): string {
                        if ($record->cashEntry?->isWaived()) {
                            $amount = (int) $record->cashEntry->amount;

                            return $amount > 0
                                ? __('Waived :amount', ['amount' => '৳'.number_format($amount)])
                                : __('Waived');
                        }

                        if ($record->cashEntry) {
                            return '৳'.number_format($record->cashEntry->amount);
                        }

                        if (in_array($record->status, ['cancelled', 'no_show'], true)) {
                            return '—';
                        }

                        return __('Due');
                    }),
            ])
            ->recordActions(RosterRecordActions::compact())
            ->headerActions([
                // Same Mark Late path as Live Queue Control — here so staff can
                // tell waiting patients the doctor is delayed without opening
                // the queue screen or pressing Start. Only before the session
                // is running (no live row, or still scheduled).
                Action::make('markLate')
                    ->label('Mark Late')
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (): bool => (auth()->user() instanceof \App\Models\User
                            && StaffDeskJobs::canRunQueue(auth()->user()))
                        && static::markableSessionOptions() !== [])
                    ->form([
                        Select::make('schedule_session_id')
                            ->label(__('Session'))
                            ->options(fn (): array => static::markableSessionOptions())
                            ->default(function (): ?int {
                                $ids = array_keys(static::markableSessionOptions());

                                return count($ids) === 1 ? (int) $ids[0] : null;
                            })
                            ->required()
                            ->native(false)
                            ->live(),
                        Select::make('delay_minutes')
                            ->label(fn (Get $get): string => static::isExtendingDelay(
                                $get('schedule_session_id') ? (int) $get('schedule_session_id') : null,
                            ) ? __('Additional delay (total)') : __('Delay Duration'))
                            ->options(fn (Get $get): array => app(SittingPrompt::class)->delayOptionsFor(
                                static::currentDelayForSession(
                                    $get('schedule_session_id') ? (int) $get('schedule_session_id') : null,
                                ),
                            ))
                            ->required()
                            ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $current = static::currentDelayForSession(
                                    $get('schedule_session_id') ? (int) $get('schedule_session_id') : null,
                                );
                                if ($current > 0 && (int) $value <= $current) {
                                    $fail(__('Choose a longer delay than the :minutes minutes already announced.', [
                                        'minutes' => $current,
                                    ]));
                                }
                            }),
                        Placeholder::make('sms_cost')
                            ->label('')
                            ->content(fn (Get $get): string => static::markLateCostWarningFor(
                                $get('schedule_session_id') ? (int) $get('schedule_session_id') : null,
                            ) ?? '')
                            ->visible(fn (Get $get): bool => filled($get('schedule_session_id'))
                                && static::markLateCostWarningFor(
                                    $get('schedule_session_id') ? (int) $get('schedule_session_id') : null,
                                ) !== null),
                    ])
                    ->action(function (array $data): void {
                        $scheduleSessionId = (int) $data['schedule_session_id'];

                        // Re-check: the form options can go stale if someone
                        // started the session in another tab.
                        if (! array_key_exists($scheduleSessionId, static::markableSessionOptions())) {
                            Notification::make()
                                ->warning()
                                ->title(__('That session has already started'))
                                ->body(__('Open Live Queue Control to manage it.'))
                                ->send();

                            return;
                        }

                        $scheduleSession = ScheduleSession::with('doctor')->findOrFail($scheduleSessionId);

                        // toDateString(), not the Carbon — same firstOrCreate
                        // trap as LiveQueueControl::markLateAction().
                        $liveSession = LiveSession::firstOrCreate([
                            'tenant_id' => tenant('id'),
                            'schedule_session_id' => $scheduleSession->id,
                            'session_date' => Carbon::today()->toDateString(),
                        ], [
                            'status' => 'delayed',
                        ]);

                        $bookings = app(LiveSessionService::class)->markDelay(
                            $liveSession,
                            (int) $data['delay_minutes'],
                        );

                        $this->delayedNotifyMinutes = (int) $data['delay_minutes'];
                        $doctor = $scheduleSession->doctor;
                        $this->delayedNotifyBookingIds = ($doctor?->wantsWhatsapp(Doctor::NOTIFY_DOCTOR_LATE) ?? false)
                            ? $bookings->pluck('id')->all()
                            : [];

                        Notification::make()->title(__('Session Delayed'))->success()->send();
                    }),

                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary')
                    ->visible(fn (): bool => (auth()->user()?->canAccessLiveQueueControl() ?? false)
                        && static::markableSessionOptions() === []),

                ActionGroup::make([
                    Action::make('manageQueueMore')
                        ->label('Manage Live Queue')
                        ->icon('heroicon-o-queue-list')
                        ->url(LiveQueueControl::getUrl())
                        ->visible(fn (): bool => (auth()->user()?->canAccessLiveQueueControl() ?? false)
                            && static::markableSessionOptions() !== []),
                    Action::make('notifyDelayed')
                        ->label('Tell waiting patients')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('warning')
                        ->modalHeading('Doctor is running late')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Done')
                        ->modalContent(function (): \Illuminate\Contracts\View\View {
                            $bookings = Booking::whereIn('id', $this->delayedNotifyBookingIds)
                                ->orderBy('serial_number')
                                ->get();
                            $minutes = $this->delayedNotifyMinutes;

                            return view('filament.tenant-admin.slot-block-notify', [
                                'bookings' => $bookings,
                                'stage' => Doctor::NOTIFY_DOCTOR_LATE,
                                'messages' => $bookings->mapWithKeys(fn (Booking $booking) => [
                                    $booking->id => __('Hello :name, the doctor is running :minutes minutes late. Your serial is :serial.', [
                                        'name' => $booking->patient_name,
                                        'minutes' => $minutes,
                                        'serial' => $booking->serial_number,
                                    ]),
                                ])->all(),
                            ]);
                        })
                        ->visible(fn (): bool => $this->delayedNotifyBookingIds !== []),
                ])
                    ->label(__('More'))
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->button(),

                Action::make('newWalkIn')
                    ->label('New Walk-In')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
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
                        Checkbox::make('seen_before_software')
                            ->label(__('They have been treated here before ChamberQ'))
                            ->helperText(__('Tick if they are an old paper-file patient. The doctor will see them as returning, not a first visit. You can also tap this on their row later.'))
                            ->default(false),
                        Select::make('referring_doctor_id')
                            ->label(__('Referred by (outside GP)'))
                            ->options(fn (): array => \App\Models\ReferringDoctor::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (\App\Models\ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                                ->all())
                            ->placeholder(__('Walk-in / no referrer'))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (): bool => tenant()?->hasReferrals() ?? false),
                        Select::make('bookable')
                            ->label(__('Session'))
                            // Resolved names, never raw ids: two sessions both
                            // labelled "Morning Shift" are indistinguishable.
                            ->options(fn () => static::todaysBookableOptions())
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->action(function (array $data, BookingService $bookingService) {
                        [$type, $id] = explode(':', $data['bookable']);

                        $bookable = $type === 'lab'
                            ? LabCollectionSlot::findOrFail($id)
                            : ScheduleSession::findOrFail($id);

                        $patientId = ($data['patient_id'] ?? null) === '__new__'
                            ? null
                            : ($data['patient_id'] ?? null);

                        try {
                            $bookingService->createBookingForBookable(
                                $bookable,
                                today()->toDateString(),
                                $data['patient_name'],
                                $data['patient_phone'],
                                [],
                                sendSms: true,
                                patientId: $patientId,
                                wantsEarlierDate: false,
                                whatsappPhone: null,
                                shareClinicalHistory: array_key_exists('share_clinical_history', $data)
                                    ? (bool) $data['share_clinical_history']
                                    : true,
                                nid: $data['nid'] ?? null,
                                yearOfBirth: filled($data['year_of_birth'] ?? null) ? (int) $data['year_of_birth'] : null,
                                allowOverflow: true,
                                allowEndedToday: true,
                                seenBeforeSoftware: ! empty($data['seen_before_software']) ? true : null,
                                allowMskWalkIn: $bookable instanceof ScheduleSession
                                    && $bookable->kind === ScheduleSession::KIND_MSK,
                                referringDoctorId: filled($data['referring_doctor_id'] ?? null)
                                    ? (int) $data['referring_doctor_id']
                                    : null,
                            );
                        } catch (BookingUnavailableException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                            $this->halt();
                        }
                    })
                    ->successNotificationTitle(__('Walk-in added to today\'s queue.'))
            ]);
    }

    /**
     * Today's bookable sessions and lab windows, labelled so staff can tell two
     * identically-named sessions apart.
     *
     * @return array<string, string>
     */
    protected static function todaysBookableOptions(): array
    {
        $today = today()->dayOfWeek;
        $options = [];

        $sessionsQuery = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $today);

        $user = auth()->user();
        if ($user instanceof \App\Models\User) {
            StaffDeskScope::constrainScheduleSessions($sessionsQuery, $user);
        }

        $sessions = $sessionsQuery->get();

        foreach ($sessions as $session) {
            $kindSuffix = (tenant()?->hasStations() && filled($session->kind))
                ? ' · '.($session->kindLabel() ?? $session->kind)
                : '';

            $options['session:' . $session->id] = sprintf(
                '%s — %s (%s, %s–%s)%s',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
                $kindSuffix,
            );
        }

        if (tenant()?->hasFeature('lab_tests')) {
            $labQuery = LabCollectionSlot::with('chamber')->where('day_of_week', $today);
            if ($user instanceof \App\Models\User) {
                $chamberIds = StaffDeskScope::chamberIdsFor($user);
                if ($chamberIds !== null) {
                    $labQuery->whereIn('chamber_id', $chamberIds);
                }
            }
            $slots = $labQuery->get();

            foreach ($slots as $slot) {
                $options['lab:' . $slot->id] = sprintf(
                    '%s — %s (%s–%s)',
                    __('Lab collection'),
                    $slot->chamber?->name ?? __('Main lab'),
                    Carbon::parse($slot->start_time)->format('g:i A'),
                    Carbon::parse($slot->end_time)->format('g:i A'),
                );
            }
        }

        return $options;
    }

    /**
     * Today's schedule sessions that can still be marked late — same rule as
     * Live Queue Control: no live row yet, or the live row is still scheduled
     * or delayed (Add time, larger total only). Once the sitting is active,
     * Mark Late is gone.
     *
     * @return array<int, string>
     */
    protected static function markableSessionOptions(): array
    {
        $todayDow = Carbon::today()->dayOfWeek;
        $todayDate = Carbon::today()->toDateString();

        $sessionsQuery = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $todayDow)
            ->orderBy('start_time');

        $user = auth()->user();
        if ($user instanceof \App\Models\User) {
            StaffDeskScope::constrainScheduleSessions($sessionsQuery, $user);
        }

        $sessions = $sessionsQuery->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $liveBySession = LiveSession::query()
            ->whereIn('schedule_session_id', $sessions->pluck('id'))
            ->where('session_date', $todayDate)
            ->get()
            ->keyBy('schedule_session_id');

        $options = [];

        foreach ($sessions as $session) {
            $live = $liveBySession->get($session->id);

            if ($live && ! in_array($live->status, ['scheduled', 'delayed'], true)) {
                continue;
            }

            $suffix = ($live && $live->status === 'delayed')
                ? ' — '.__('delayed :minutes min', ['minutes' => $live->delay_minutes])
                : '';

            $options[$session->id] = sprintf(
                '%s — %s (%s, %s–%s)%s',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
                $suffix,
            );
        }

        return $options;
    }

    /**
     * What Mark Late is about to spend for one session — same wording as Live
     * Queue Control. Null when late SMS is off or nobody is waiting.
     */
    protected static function markLateCostWarningFor(?int $scheduleSessionId): ?string
    {
        if (! $scheduleSessionId) {
            return null;
        }

        $session = ScheduleSession::with('doctor')->find($scheduleSessionId);
        $doctor = $session?->doctor;

        if (! $doctor?->wantsSms(Doctor::NOTIFY_DOCTOR_LATE)) {
            return null;
        }

        $waiting = Booking::query()
            ->where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $scheduleSessionId)
            ->where('booking_date', Carbon::today()->toDateString())
            ->whereIn('status', ['waiting', 'called', 'skipped'])
            ->count();

        if ($waiting === 0) {
            return null;
        }

        $balance = (int) (tenant()->sms_balance ?? 0);

        $warning = __('This texts :count waiting patient(s) and uses :count SMS credit(s). Balance after: :left.', [
            'count' => $waiting,
            'left' => max(0, $balance - $waiting),
        ]);

        if ($balance < $waiting) {
            $warning .= ' '.__('Only :balance credit(s) left, so the last :short patient(s) will not get a text.', [
                'balance' => $balance,
                'short' => $waiting - $balance,
            ]);
        }

        return $warning;
    }

    protected static function currentDelayForSession(?int $scheduleSessionId): int
    {
        if (! $scheduleSessionId) {
            return 0;
        }

        $live = LiveSession::query()
            ->where('schedule_session_id', $scheduleSessionId)
            ->where('session_date', Carbon::today()->toDateString())
            ->first();

        return (int) ($live?->delay_minutes ?? 0);
    }

    protected static function isExtendingDelay(?int $scheduleSessionId): bool
    {
        if (! $scheduleSessionId) {
            return false;
        }

        $live = LiveSession::query()
            ->where('schedule_session_id', $scheduleSessionId)
            ->where('session_date', Carbon::today()->toDateString())
            ->first();

        return $live?->status === 'delayed';
    }
}
