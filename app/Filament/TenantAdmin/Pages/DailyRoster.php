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
use App\Filament\TenantAdmin\Support\CompleteBookingWithVisitNotes;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use Carbon\Carbon;
use App\Services\BookingService;
use App\Services\ChamberCashService;
use App\Services\LiveSessionService;
use App\Services\MedicineService;
use App\Services\PatientService;
use App\Services\VisitRecordService;
use Filament\Notifications\Notification;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
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

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageQueue() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('booking_date', today()->toDateString())
                    ->with(['visitRecord.prescription', 'cashEntry', 'labTests'])
                    ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')->label('Serial'),
                TextColumn::make('patient_name')->label('Name')->searchable(),
                TextColumn::make('patient_phone')->label('Phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
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
            ->recordActions([
                Action::make('call')
                    ->label('Call to Chamber')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => (tenant()?->hasLiveQueue() ?? false)
                        && (auth()->user()?->canOperateQueueControls() ?? false)
                        && $record->status === 'waiting')
                    ->action(function (Booking $record): void {
                        // Refused while someone else is mid-consult. Say so —
                        // silently doing nothing looks like a broken button.
                        if (app(LiveSessionService::class)->bringBookingToChamber($record)) {
                            return;
                        }

                        Notification::make()
                            ->warning()
                            ->title(__('Someone is still with the doctor'))
                            ->body(__('Complete the patient currently in the chamber before calling #:serial in.', [
                                'serial' => $record->serial_number,
                            ]))
                            ->send();
                    }),

                // Front-door day list (no live queue): mark arrival without Call next.
                Action::make('arrived')
                    ->label(__('Arrived'))
                    ->color('info')
                    ->visible(fn (Booking $record): bool => ! (tenant()?->hasLiveQueue() ?? true)
                        && (auth()->user()?->canManageQueue() ?? false)
                        && $record->status === 'waiting')
                    ->action(function (Booking $record): void {
                        $record->update(['status' => 'in_chamber']);

                        Notification::make()
                            ->title(__('Marked arrived'))
                            ->success()
                            ->send();
                    }),

                Action::make('noShow')
                    ->label(__('No-show'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => ! (tenant()?->hasLiveQueue() ?? true)
                        && (auth()->user()?->canManageQueue() ?? false)
                        && in_array($record->status, ['waiting', 'in_chamber', 'called'], true))
                    ->action(function (Booking $record): void {
                        $record->update(['status' => 'no_show']);

                        Notification::make()
                            ->title(__('Marked no-show'))
                            ->success()
                            ->send();
                    }),

                VisitNotesFormSchema::configureModal(Action::make('complete'))
                    ->label('Mark Completed')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => static::canCompleteFromRoster($record)
                        && in_array($record->status, ['waiting', 'in_chamber', 'called'], true))
                    ->form(fn (Booking $record): array => auth()->user()?->canRecordVisitNotes()
                        ? VisitNotesFormSchema::components(
                            $record->patient,
                            app(VisitRecordService::class)->lastRecordedVisitForPatient($record->patient, $record->id),
                            $record,
                        )
                        : [])
                    ->modalHeading(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                        ? __('Complete visit')
                        : null)
                    ->modalDescription(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                        ? __('Add optional notes, or leave everything blank and tap Complete.')
                        : null)
                    ->modalSubmitActionLabel(__('Complete'))
                    ->action(function (
                        Booking $record,
                        array $data,
                        LiveSessionService $liveSessionService,
                        VisitRecordService $visitRecordService,
                    ): void {
                        CompleteBookingWithVisitNotes::finish(
                            $record,
                            $data,
                            $liveSessionService,
                            $visitRecordService,
                        );
                    }),

                // Staff typing up a paper prescription after the patient left.
                // Only appears for doctors who switched the delegation on.
                VisitNotesFormSchema::configureModal(Action::make('enterPrescription'))
                    ->label(fn (Booking $record): string => $record->visitRecord?->prescription
                        ? __('Edit prescription')
                        : __('Enter prescription'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => static::staffMayEnterPrescriptionFor($record))
                    ->modalHeading(__('Enter paper prescription'))
                    ->modalDescription(__('Copy in what the doctor wrote by hand. This does not change the visit status.'))
                    ->modalSubmitActionLabel(__('Save prescription'))
                    ->fillForm(fn (Booking $record): array => VisitNotesFormSchema::staffStateFromRecord($record->visitRecord))
                    ->form(fn (Booking $record): array => VisitNotesFormSchema::staffPrescriptionComponents($record))
                    ->action(function (Booking $record, array $data, VisitRecordService $visitRecordService): void {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();

                        $visitRecordService->saveStaffEnteredPrescription($record, $user, $data);

                        Notification::make()
                            ->title(__('Prescription saved'))
                            ->success()
                            ->send();
                    }),

                Action::make('collectFee')
                    ->label(fn (Booking $record): string => $record->cashEntry
                        ? __('Edit fee')
                        : __('Collect fee'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => (auth()->user()?->canManageCash() ?? false)
                        && ! in_array($record->status, ['cancelled', 'no_show'], true))
                    ->fillForm(function (Booking $record): array {
                        $entry = $record->cashEntry;

                        return [
                            'amount' => $entry?->amount ?? app(ChamberCashService::class)->suggestedAmountTaka($record),
                            'method' => $entry?->method ?? ChamberCashEntry::METHOD_CASH,
                            'waived' => $entry?->isWaived() ?? false,
                            'note' => $entry?->note,
                        ];
                    })
                    ->form([
                        TextInput::make('amount')
                            ->label(__('Amount (৳)'))
                            ->numeric()
                            ->minValue(0)
                            ->required(fn (Get $get): bool => ! $get('waived')),
                        Select::make('method')
                            ->label(__('Paid how'))
                            ->options(ChamberCashEntry::methods())
                            ->required()
                            ->native(false),
                        Checkbox::make('waived')
                            ->label(__('Waive this fee'))
                            ->live(),
                        TextInput::make('note')
                            ->label(__('Note')),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();

                        app(ChamberCashService::class)->recordPatientIncome(
                            $record,
                            $user,
                            (int) ($data['amount'] ?? 0),
                            $data['method'],
                            waived: (bool) ($data['waived'] ?? false),
                            note: filled($data['note'] ?? null) ? (string) $data['note'] : null,
                        );

                        Notification::make()
                            ->title(($data['waived'] ?? false) ? __('Fee waived') : __('Fee collected'))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                // Same Mark Late path as Live Queue Control — here so staff can
                // tell waiting patients the doctor is delayed without opening
                // the queue screen or pressing Start. Only before the session
                // is running (no live row, or still scheduled).
                Action::make('markLate')
                    ->label(__('Mark Late'))
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (): bool => (auth()->user()?->canManageQueue() ?? false)
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
                            ->label(__('Delay Duration'))
                            ->options([
                                15 => '15 minutes',
                                30 => '30 minutes',
                                45 => '45 minutes',
                                60 => '1 hour',
                                90 => '1.5 hours',
                                120 => '2 hours',
                            ])
                            ->required(),
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

                Action::make('notifyDelayed')
                    ->label(__('Tell waiting patients'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->modalHeading(__('Doctor is running late'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Done'))
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

                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary')
                    ->visible(fn (): bool => auth()->user()?->canAccessLiveQueueControl() ?? false),

                Action::make('newWalkIn')
                    ->label(__('New Walk-In'))
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        TextInput::make('patient_phone')
                            ->label(__('Phone number'))
                            ->extraInputAttributes(['name' => 'patient_phone'])
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
                            ->extraInputAttributes(['name' => 'patient_name'])
                            ->autocomplete('name')
                            ->required(),
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

                        $bookingService->createBookingForBookable(
                            $bookable,
                            today()->toDateString(),
                            $data['patient_name'],
                            $data['patient_phone'],
                            [],
                            true,
                            $patientId,
                            false,
                            null,
                            array_key_exists('share_clinical_history', $data)
                                ? (bool) $data['share_clinical_history']
                                : true,
                            $data['nid'] ?? null,
                        );
                    })
                    ->successNotificationTitle(__('Walk-in added to today\'s queue.'))
            ]);
    }

    /**
     * Staff-only affordance: doctors already have the full notes modal, so
     * showing them a second, narrower prescription form would just be confusing.
     */
    protected static function staffMayEnterPrescriptionFor(Booking $booking): bool
    {
        if (! tenant()?->hasPrescription()) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user?->isStaff() || $booking->status !== 'completed') {
            return false;
        }

        return $user->canEnterPrescriptionFor(
            app(MedicineService::class)->resolvePrescribingDoctor($booking)
        );
    }

    /**
     * Live-queue runners keep Call-next ownership; front-door-only day lists
     * let any queue-capable staff mark Done without Live Queue Control.
     */
    protected static function canCompleteFromRoster(Booking $booking): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (tenant()?->hasLiveQueue()) {
            return $user->canOperateQueueControls();
        }

        return $user->canManageQueue();
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

        $sessions = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $today)
            ->get();

        foreach ($sessions as $session) {
            $options['session:' . $session->id] = sprintf(
                '%s — %s (%s, %s–%s)',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
            );
        }

        if (tenant()?->hasFeature('lab_tests')) {
            $slots = LabCollectionSlot::with('chamber')->where('day_of_week', $today)->get();

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
     * Live Queue Control: no live row yet, or the live row is still scheduled.
     *
     * @return array<int, string>
     */
    protected static function markableSessionOptions(): array
    {
        $todayDow = Carbon::today()->dayOfWeek;
        $todayDate = Carbon::today()->toDateString();

        $sessions = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $todayDow)
            ->orderBy('start_time')
            ->get();

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

            if ($live && $live->status !== 'scheduled') {
                continue;
            }

            $options[$session->id] = sprintf(
                '%s — %s (%s, %s–%s)',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
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
}
