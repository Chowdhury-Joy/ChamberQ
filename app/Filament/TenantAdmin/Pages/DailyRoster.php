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
use App\Filament\TenantAdmin\Support\PatientContinuityActions;
use App\Filament\TenantAdmin\Resources\Patients\Schemas\PatientForm;
use App\Filament\TenantAdmin\Support\StationsCollectFeeForm;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Filament\TenantAdmin\Support\VisitPaperScanForm;
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
use App\Services\RepeatBookingService;
use App\Services\SittingPrompt;
use App\Services\StationsHandoffService;
use App\Services\VisitRecordService;
use Filament\Notifications\Notification;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
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
            ->recordActions([
                PatientContinuityActions::toggleSeenBeforeSoftware(
                    Action::make('toggleSeenBeforeSoftware'),
                ),
                VisitPaperScanForm::scanAction(
                    Action::make('scanPapers'),
                ),
                Action::make('call')
                    ->label('Call to Chamber')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => (tenant()?->hasLiveQueue() ?? false)
                        && (auth()->user() instanceof \App\Models\User
                            && StaffDeskJobs::canRunQueue(auth()->user()))
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
                    ->label('Arrived')
                    ->color('info')
                    ->visible(fn (Booking $record): bool => ! (tenant()?->hasLiveQueue() ?? true)
                        && (auth()->user() instanceof \App\Models\User
                            && (StaffDeskJobs::canRunQueue(auth()->user())
                                || (! (tenant()?->hasLiveQueue() ?? true) && auth()->user()->canManageQueue())))
                        && $record->status === 'waiting')
                    ->action(function (Booking $record): void {
                        $record->update(['status' => 'in_chamber']);

                        Notification::make()
                            ->title(__('Marked arrived'))
                            ->success()
                            ->send();
                    }),

                Action::make('noShow')
                    ->label('No-show')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => ! (tenant()?->hasLiveQueue() ?? true)
                        && (auth()->user() instanceof \App\Models\User
                            && (StaffDeskJobs::canRunQueue(auth()->user())
                                || (! (tenant()?->hasLiveQueue() ?? true) && auth()->user()->canManageQueue())))
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
                    ->modalSubmitActionLabel('Complete')
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
                        ? 'Edit prescription'
                        : 'Enter prescription')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => static::staffMayEnterPrescriptionFor($record))
                    ->modalHeading('Enter paper prescription')
                    ->modalDescription(__('Copy in what the doctor wrote by hand. This does not change the visit status.'))
                    ->modalSubmitActionLabel('Save prescription')
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

                Action::make('recordVitals')
                    ->label(__('Outdoor vitals'))
                    ->icon('heroicon-o-heart')
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => (tenant()?->hasStations() ?? false)
                        && (auth()->user() instanceof \App\Models\User
                            && StaffDeskJobs::canRecordPrep(auth()->user()))
                        && $record->status === 'waiting')
                    ->fillForm(fn (Booking $record): array => [
                        'weight_kg' => $record->visitRecord?->weight_kg,
                        'bp_systolic' => $record->visitRecord?->bp_systolic,
                        'bp_diastolic' => $record->visitRecord?->bp_diastolic,
                    ])
                    ->schema([
                        TextInput::make('weight_kg')
                            ->label(__('Weight (kg)'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('bp_systolic')
                            ->label(__('BP systolic'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('bp_diastolic')
                            ->label(__('BP diastolic'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->action(function (Booking $record, array $data, VisitRecordService $visitRecordService): void {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();

                        try {
                            $visitRecordService->saveStaffVitals($record, $user, $data);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Vitals saved'))
                            ->success()
                            ->send();
                    }),

                \App\Filament\TenantAdmin\Support\StationsHandoffForm::bookAction(
                    Action::make('sendToIntervention'),
                ),

                \App\Filament\TenantAdmin\Support\StationsHandoffForm::moveAction(
                    Action::make('moveIntervention'),
                ),

                Action::make('markPrepped')
                    ->label(__('Mark prepped'))
                    ->visible(fn (Booking $record): bool => static::canAdvanceProcedure($record, Booking::PROCEDURE_LOGGED))
                    ->action(fn (Booking $record, StationsHandoffService $handoff) => static::advanceProcedure($record, $handoff, Booking::PROCEDURE_PREPPED)),

                Action::make('callDoctorForProcedure')
                    ->label(__('Call doctor'))
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => static::canAdvanceProcedure($record, Booking::PROCEDURE_PREPPED))
                    ->action(fn (Booking $record, StationsHandoffService $handoff) => static::advanceProcedure($record, $handoff, Booking::PROCEDURE_DOCTOR_CALLED)),

                Action::make('procedureDone')
                    ->label(__('Procedure done'))
                    ->color('success')
                    ->visible(fn (Booking $record): bool => static::canAdvanceProcedure($record, Booking::PROCEDURE_DOCTOR_CALLED))
                    ->action(fn (Booking $record, StationsHandoffService $handoff) => static::advanceProcedure($record, $handoff, Booking::PROCEDURE_DONE)),

                \App\Filament\TenantAdmin\Support\StationsHandoffForm::sendToCounselingAction(
                    Action::make('sendToCounseling'),
                ),

                Action::make('collectFee')
                    ->label(fn (Booking $record): string => $record->cashEntry
                        ? 'Edit fee'
                        : 'Collect fee')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => (auth()->user() instanceof \App\Models\User
                            && StaffDeskJobs::canCollectFee(auth()->user()))
                        && ! in_array($record->status, ['cancelled', 'no_show'], true)
                        && ! static::shouldHideCollectFee($record))
                    ->fillForm(function (Booking $record): array {
                        if (tenant()?->hasStations()) {
                            return StationsCollectFeeForm::fillFromEntry($record);
                        }

                        $entry = $record->cashEntry;
                        $doctor = Doctor::resolveForBooking($record);
                        $feeType = $entry?->fee_type ?? Doctor::FEE_CONSULTATION;
                        if ($doctor && ! array_key_exists($feeType, $doctor->feeTypes())) {
                            $feeType = Doctor::FEE_CONSULTATION;
                        }

                        return [
                            'fee_type' => $feeType,
                            'method' => $entry?->method ?? ChamberCashEntry::METHOD_CASH,
                            'cash_taka' => $entry?->cash_taka ?? 0,
                            'online_taka' => $entry?->mobile_taka ?? 0,
                            'online_method' => $entry?->mobile_method,
                            'waived' => $entry?->isWaived() ?? false,
                            'note' => $entry?->note,
                        ];
                    })
                    ->form(function (Booking $record): array {
                        if (tenant()?->hasStations()) {
                            return StationsCollectFeeForm::components($record);
                        }

                        $hasExtras = Doctor::resolveForBooking($record)?->hasExtraFeeTypes() ?? false;

                        return [
                            $hasExtras
                                ? Select::make('fee_type')
                                    ->label(__('Visit type'))
                                    ->options(fn (): array => app(ChamberCashService::class)->feeTypeOptions($record))
                                    ->required()
                                    ->live()
                                    ->native(false)
                                : Hidden::make('fee_type')
                                    ->default(Doctor::FEE_CONSULTATION),
                            Placeholder::make('amount_due')
                                ->label(__('Amount'))
                                ->content(function (Get $get) use ($record): string {
                                    $type = (string) ($get('fee_type') ?: Doctor::FEE_CONSULTATION);
                                    try {
                                        $taka = app(ChamberCashService::class)->amountForFeeType($record, $type);
                                    } catch (\InvalidArgumentException) {
                                        $taka = app(ChamberCashService::class)->suggestedAmountTaka($record);
                                    }

                                    return '৳'.number_format($taka);
                                })
                                ->helperText(__('Set on the doctor\'s fee list. Staff cannot type an amount.')),
                            Select::make('method')
                                ->label(__('Paid how'))
                                ->options(ChamberCashEntry::methods())
                                ->required()
                                ->live()
                                ->native(false),
                            TextInput::make('cash_taka')
                                ->label(__('Cash (৳)'))
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                                    && ! (bool) $get('waived'))
                                ->rules([
                                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                        if ($get('method') !== ChamberCashEntry::METHOD_MIXED || (bool) $get('waived')) {
                                            return;
                                        }

                                        try {
                                            $fee = app(ChamberCashService::class)->amountForFeeType(
                                                $record,
                                                (string) ($get('fee_type') ?: Doctor::FEE_CONSULTATION),
                                            );
                                        } catch (\InvalidArgumentException) {
                                            return;
                                        }

                                        if ((int) $get('cash_taka') + (int) $get('online_taka') !== $fee) {
                                            $fail(__('Cash plus online must equal the total amount.'));
                                        }
                                    },
                                ]),
                            TextInput::make('online_taka')
                                ->label(__('Online (৳)'))
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                                    && ! (bool) $get('waived')),
                            Select::make('online_method')
                                ->label(__('Online method'))
                                ->options(ChamberCashEntry::onlineMethods())
                                ->native(false)
                                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                                    && ! (bool) $get('waived')
                                    && (int) ($get('online_taka') ?? 0) > 0)
                                ->required(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                                    && ! (bool) $get('waived')
                                    && (int) ($get('online_taka') ?? 0) > 0),
                            Checkbox::make('waived')
                                ->label(__('Waive this fee'))
                                ->live(),
                            TextInput::make('note')
                                ->label(__('Note')),
                        ];
                    })
                    ->action(function (Booking $record, array $data): void {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();

                        if (tenant()?->hasStations()) {
                            try {
                                StationsCollectFeeForm::save($record, $data, $user);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(($data['waived'] ?? false) ? __('Fee waived') : __('Fee collected'))
                                ->success()
                                ->send();

                            return;
                        }

                        try {
                            app(ChamberCashService::class)->recordPatientIncome(
                                $record,
                                $user,
                                $data['method'],
                                waived: (bool) ($data['waived'] ?? false),
                                note: filled($data['note'] ?? null) ? (string) $data['note'] : null,
                                feeType: (string) ($data['fee_type'] ?? Doctor::FEE_CONSULTATION),
                                cashTaka: isset($data['cash_taka']) ? (int) $data['cash_taka'] : null,
                                onlineTaka: isset($data['online_taka']) ? (int) $data['online_taka'] : null,
                                onlineMethod: filled($data['online_method'] ?? null) ? (string) $data['online_method'] : null,
                            );
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(($data['waived'] ?? false) ? __('Fee waived') : __('Fee collected'))
                            ->success()
                            ->send();
                    }),

                Action::make('repeatSerial')
                    ->label('Repeat sitting')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Booking $record): bool => ! in_array($record->status, ['cancelled', 'no_show'], true)
                        && app(RepeatBookingService::class)->doctorAllowsRepeat($record))
                    ->form(function (Booking $record): array {
                        return [
                            Select::make('weeks')
                                ->label(__('How many more sittings?'))
                                ->options(array_combine(
                                    range(1, RepeatBookingService::MAX_WEEKS),
                                    range(1, RepeatBookingService::MAX_WEEKS),
                                ))
                                ->default(4)
                                ->required()
                                ->live()
                                ->native(false),
                            Placeholder::make('dates_preview')
                                ->label(__('Dates that will get a serial'))
                                ->content(function (Get $get) use ($record): string {
                                    $weeks = (int) ($get('weeks') ?: 4);
                                    try {
                                        $plan = app(RepeatBookingService::class)->planDates($record, $weeks);
                                    } catch (\InvalidArgumentException $e) {
                                        return $e->getMessage();
                                    }

                                    if ($plan['dates'] === []) {
                                        return __('No open sittings in the next weeks.');
                                    }

                                    $lines = array_map(
                                        fn (string $date): string => Carbon::parse($date)->toFormattedDateString(),
                                        $plan['dates'],
                                    );
                                    $skipNote = $plan['skipped'] === []
                                        ? ''
                                        : ' '.__('(:count dates skipped — full or closed)', [
                                            'count' => count($plan['skipped']),
                                        ]);

                                    return implode(', ', $lines).$skipNote;
                                }),
                        ];
                    })
                    ->action(function (Booking $record, array $data): void {
                        try {
                            $result = app(RepeatBookingService::class)->repeatFromBooking(
                                $record,
                                (int) $data['weeks'],
                            );
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Booked :count more sittings', ['count' => count($result['created'])]))
                            ->body($result['skipped'] === []
                                ? null
                                : __('Skipped :count dates that were full or closed.', [
                                    'count' => count($result['skipped']),
                                ]))
                            ->success()
                            ->send();
                    }),

                Action::make('cancelRepeatRemainder')
                    ->label('Cancel later sittings')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel later sittings')
                    ->modalDescription(__('This visit stays. Later dates in this repeating series will be cancelled.'))
                    ->visible(fn (Booking $record): bool => filled($record->repeat_series_id)
                        && Booking::query()
                            ->where('repeat_series_id', $record->repeat_series_id)
                            ->where('id', '!=', $record->id)
                            ->where('booking_date', '>', $record->booking_date->toDateString())
                            ->whereIn('status', ['waiting', 'called', 'skipped'])
                            ->exists())
                    ->action(function (Booking $record): void {
                        $count = app(RepeatBookingService::class)->cancelRemainder($record);

                        Notification::make()
                            ->title(__('Cancelled :count later sittings', ['count' => $count]))
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

                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary')
                    ->visible(fn (): bool => auth()->user()?->canAccessLiveQueueControl() ?? false),

                Action::make('newWalkIn')
                    ->label('New Walk-In')
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
                            sendSms: true,
                            patientId: $patientId,
                            wantsEarlierDate: false,
                            whatsappPhone: null,
                            shareClinicalHistory: array_key_exists('share_clinical_history', $data)
                                ? (bool) $data['share_clinical_history']
                                : true,
                            nid: $data['nid'] ?? null,
                            yearOfBirth: filled($data['year_of_birth'] ?? null) ? (int) $data['year_of_birth'] : null,
                            seenBeforeSoftware: ! empty($data['seen_before_software']) ? true : null,
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
            return StaffDeskJobs::canRunQueue($user);
        }

        return $user->isStaff()
            ? StaffDeskJobs::hasJob($user, StaffDeskJobs::JOB_QUEUE) && $user->canManageQueue()
            : $user->canManageQueue();
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

    protected static function shouldHideCollectFee(Booking $booking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession && $session->isFreeKind();
    }

    protected static function canAdvanceProcedure(Booking $booking, string $expectedStatus): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if (! auth()->user() instanceof \App\Models\User
            || ! StaffDeskJobs::canRecordPrep(auth()->user())) {
            return false;
        }

        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession
            && $session->isInterventionKind()
            && ($booking->procedure_status ?? Booking::PROCEDURE_LOGGED) === $expectedStatus
            && ! in_array($booking->status, ['cancelled', 'no_show', 'completed'], true);
    }

    protected static function advanceProcedure(
        Booking $booking,
        StationsHandoffService $handoff,
        string $status,
    ): void {
        try {
            $handoff->advanceProcedureStatus($booking, $status);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(Booking::procedureStatusOptions()[$status] ?? __('Updated'))
            ->success()
            ->send();
    }
}
