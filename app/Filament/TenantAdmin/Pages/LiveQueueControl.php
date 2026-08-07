<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Concerns\AppliesVisitNotesDrafts;
use App\Filament\TenantAdmin\Support\CompleteBookingWithVisitNotes;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use App\Services\PatientService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Services\BookingService;
use App\Services\VisitRecordService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use App\Models\Booking;

class LiveQueueControl extends Page implements HasActions, HasTable
{
    use AppliesVisitNotesDrafts;
    use InteractsWithActions, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Live Queue Control';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.tenant-admin.pages.live-queue-control';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canAccessLiveQueueControl() ?? false;
    }

    public $selectedSessionId = null;

    public function mount()
    {
        // Prefer the most recently started live session for today.
        $activeLiveSession = LiveSession::whereDate('session_date', Carbon::today())
            ->whereIn('status', ['active', 'paused', 'delayed'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($activeLiveSession) {
            $this->selectedSessionId = $activeLiveSession->schedule_session_id;

            return;
        }

        // A solo chamber usually has exactly one session today — making staff
        // pick it from a dropdown every morning is a step with no decision in it.
        if ($this->sessions->count() === 1) {
            $this->selectedSessionId = $this->sessions->keys()->first();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Session lifecycle actions live behind one menu so the destructive
            // ones (cancel / end) are never a mis-tap away from the routine one.
            \Filament\Actions\ActionGroup::make([
                $this->markLateAction(),
                $this->pauseSessionAction(),
                $this->resumeSessionAction(),
                $this->markAbsentAction(),
                $this->endSessionAction(),
            ])
                ->label(__('Session actions'))
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button()
                ->visible(fn () => $this->selectedSessionId !== null),

            \Filament\Actions\Action::make('newWalkIn')
                ->label(__('New Walk-In'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn () => $this->selectedSessionId !== null)
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
                ])
                ->action(function (array $data, BookingService $bookingService) {
                    $bookable = ScheduleSession::findOrFail($this->selectedSessionId);

                    $patientId = ($data['patient_id'] ?? null) === '__new__'
                        ? null
                        : ($data['patient_id'] ?? null);

                    $bookingService->createBookingForBookable(
                        $bookable,
                        Carbon::today()->toDateString(),
                        $data['patient_name'],
                        $data['patient_phone'],
                        [],
                        true,
                        $patientId,
                    );
                })
                ->successNotificationTitle(__('Walk-in added directly to this session\'s live queue.')),
        ];
    }
    
    public function endSessionAction(): Action
    {
        return Action::make('endSession')
            ->label('Finish / End Session')
            ->color('danger')
            ->outlined()
            ->icon('heroicon-s-flag')
            ->requiresConfirmation()
            ->modalDescription('Are you sure you want to completely end this session? All remaining patients will be cancelled.')
            ->modalSubmitActionLabel(__('End session'))
            ->action(function () {
                if (!$this->activeLiveSession) return;
                $catchUpCount = 0;
                if (auth()->user()?->canRecordVisitNotes()) {
                    $catchUpCount = app(VisitRecordService::class)->countCompletedBookingsWithoutNotesToday(
                        $this->activeLiveSession,
                    );
                }
                app(LiveSessionService::class)->endSession($this->activeLiveSession);
                if ($catchUpCount > 0 && auth()->user()?->canRecordVisitNotes()) {
                    Notification::make()
                        ->title(__('Session ended'))
                        ->body(__(':count patients today without notes — open Consult Screen to fill in while the evening is fresh.', [
                            'count' => $catchUpCount,
                        ]))
                        ->warning()
                        ->duration(12000)
                        ->send();
                } else {
                    Notification::make()->title('Session Ended')->success()->send();
                }
            })
            ->visible(fn () => $this->activeLiveSession && in_array($this->activeLiveSession->status, ['active', 'paused']));
    }

    public function getSessionsProperty()
    {
        $today = Carbon::today()->dayOfWeek;

        return ScheduleSession::with('chamber')
            ->where('day_of_week', $today)
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(function ($session) {
                $chamber = $session->chamber?->name ?? 'Chamber';
                $label = $chamber.' — '.$session->session_name.' ('.$session->start_time.'–'.$session->end_time.')';

                return [$session->id => $label];
            });
    }

    /**
     * Livewire caches `getXProperty()` results for the whole request, so an
     * action that moves the queue would re-render against the state it just
     * replaced. Every mutating action clears them.
     */
    private function forgetQueueState(): void
    {
        unset($this->activeLiveSession, $this->bookings, $this->queueStats);
    }

    public function getActiveLiveSessionProperty()
    {
        if (!$this->selectedSessionId) return null;

        return LiveSession::where('schedule_session_id', $this->selectedSessionId)
            ->whereDate('session_date', Carbon::today())
            ->with(['currentBooking.visitRecord.prescription.items'])
            ->first();
    }

    public function getBookingsProperty()
    {
        if (!$this->selectedSessionId) return collect();

        return Booking::where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $this->selectedSessionId)
            ->whereDate('booking_date', Carbon::today())
            ->orderBy('serial_number')
            ->get();
    }

    /**
     * "How many are left and when do we finish" — the two questions staff and
     * the doctor ask all session, previously answerable only by counting rows.
     */
    public function getQueueStatsProperty(): array
    {
        $bookings = $this->bookings;

        $waiting = $bookings->whereIn('status', ['waiting', 'skipped'])->count();
        $completed = $bookings->where('status', 'completed');

        $observed = $completed
            ->filter(fn (Booking $b) => $b->in_chamber_at && $b->completed_at)
            ->map(fn (Booking $b) => $b->in_chamber_at->diffInMinutes($b->completed_at))
            ->filter(fn ($minutes) => $minutes > 0);

        $avgMinutes = $observed->isNotEmpty()
            ? (int) round($observed->avg())
            : ($this->activeLiveSession?->avgConsultationMinutes() ?? 0);

        return [
            'waiting' => $waiting,
            'done' => $completed->count(),
            'no_show' => $bookings->where('status', 'no_show')->count(),
            'avg_minutes' => $avgMinutes,
            'avg_is_observed' => $observed->isNotEmpty(),
            'finishes_at' => ($waiting > 0 && $avgMinutes > 0)
                ? Carbon::now()->addMinutes($waiting * $avgMinutes)
                : null,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->when($this->selectedSessionId, function ($query) {
                        $query->where('bookable_type', ScheduleSession::class)
                              ->where('bookable_id', $this->selectedSessionId)
                              ->whereDate('booking_date', Carbon::today());
                    }, function ($query) {
                        $query->whereRaw('1 = 0');
                    })
                    // The patient being called must sit at the top — an earlier
                    // version left 'called' out of this list, so the person the
                    // chamber was announcing sank below cancelled bookings.
                    ->orderByRaw("CASE status"
                        ." WHEN 'called' THEN 1"
                        ." WHEN 'in_chamber' THEN 2"
                        ." WHEN 'waiting' THEN 3"
                        ." WHEN 'skipped' THEN 4"
                        ." WHEN 'completed' THEN 5"
                        ." WHEN 'no_show' THEN 6"
                        ." WHEN 'cancelled' THEN 7"
                        ." ELSE 8 END")
                    ->orderBy('serial_number')
            )
            ->recordClasses(fn (Booking $record) => match ($record->status) {
                'called' => 'fi-ta-row-called',
                'in_chamber' => 'fi-ta-row-in-chamber',
                default => null,
            })
            ->columns([
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('patient_name')
                    ->label('Patient Details')
                    ->description(fn (Booking $record) => $record->patient_phone)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'gray',
                        'called' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'info',
                        'skipped' => 'warning',
                        'no_show' => 'danger',
                        'cancelled' => 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(function ($state, Booking $record) {
                        if ($state === 'skipped') return "Skipped ({$record->skip_count}/2)";
                        return str_replace('_', ' ', Str::title($state));
                    }),
                TextColumn::make('retry_queue_position')
                    ->label('Back in queue')
                    ->formatStateUsing(fn ($state, Booking $record) => $state && $record->status === 'skipped'
                        ? 'After #'.($state - 1)
                        : null)
                    ->color('warning')
                    ->visible(fn () => $this->bookings
                        ->where('status', 'skipped')
                        ->whereNotNull('retry_queue_position')
                        ->count() > 0),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('callNow')
                    ->label('Call now')
                    ->icon('heroicon-m-megaphone')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Booking $record) => "Call #{$record->serial_number} out of turn?")
                    ->modalDescription('Anyone already called but not yet arrived goes back to waiting — they keep their place and get no skip strike.')
                    ->modalSubmitActionLabel('Call now')
                    // Hidden while someone is with the doctor: a consult in
                    // progress must never be interrupted by a queue jump.
                    ->visible(fn (Booking $record) => in_array($record->status, ['waiting', 'skipped'], true)
                        && $this->activeLiveSession?->status === 'active'
                        && $this->activeLiveSession?->currentBooking?->status !== 'in_chamber')
                    ->action(fn (Booking $record) => $this->callPatientNow($record->id)),

                \Filament\Actions\Action::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->visible(fn (Booking $record) => $record->status === 'no_show')
                    ->action(fn (Booking $record) => $this->reinstatePatient($record->id)),
            ])
            ->poll('3s')
            ->paginated(false);
    }

    public function startSession()
    {
        if (!$this->selectedSessionId) return;
        
        $scheduleSession = ScheduleSession::findOrFail($this->selectedSessionId);
        
        app(LiveSessionService::class)->startSession($scheduleSession);
        
        Notification::make()->title('Session Started')->success()->send();
        $this->dispatchCallAnnounce();
    }

    /**
     * Close the consult without advancing — the prescription is printed or sent
     * while the patient is still in the room. `callNextPatientOnly()` moves the
     * queue on once they have left.
     */
    public function completeVisit()
    {
        if (! $this->activeLiveSession) {
            return;
        }

        if (auth()->user()?->canRecordVisitNotes()) {
            $this->mountAction('completeVisit');

            return;
        }

        app(LiveSessionService::class)->completeCurrentPatientWithoutAdvancing($this->activeLiveSession);
        $this->forgetQueueState();

        Notification::make()->title('Visit completed')->success()->send();
    }

    public function callNextPatientOnly()
    {
        if (! $this->activeLiveSession) {
            return;
        }

        app(LiveSessionService::class)->callNextPatient($this->activeLiveSession);
        $this->forgetQueueState();

        // No toast here on purpose — this fires dozens of times a session and
        // the card already shows the new serial, name and status.
        $this->dispatchCallAnnounce();
    }

    public function callPatientNow($bookingId)
    {
        if (! $this->activeLiveSession) {
            return;
        }

        $booking = Booking::findOrFail($bookingId);
        $called = app(LiveSessionService::class)->callSpecificPatient($this->activeLiveSession, $booking);

        if (! $called) {
            Notification::make()
                ->title('Could not call that patient')
                ->body('Finish the patient currently in the chamber first.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatchCallAnnounce();
    }

    public function completeVisitAction(): Action
    {
        return Action::make('completeVisit')
            ->label(__('Complete visit'))
            ->form(function (Action $action): array {
                if (! auth()->user()?->canRecordVisitNotes()) {
                    return [];
                }

                $arguments = $action->getArguments();
                $forceForm = (bool) ($arguments['forceForm'] ?? false);
                $booking = $this->activeLiveSession?->currentBooking;
                $record = $booking?->visitRecord;

                if ($record?->hasClinicalContent() && ! $forceForm) {
                    return VisitNotesFormSchema::summaryComponents($record);
                }

                $patient = $booking?->patient;
                $lastVisit = $patient
                    ? app(VisitRecordService::class)->lastRecordedVisitForPatient($patient, $booking?->id)
                    : null;

                return VisitNotesFormSchema::components($patient, $lastVisit);
            })
            ->fillForm(function (): array {
                $booking = $this->activeLiveSession?->currentBooking;

                return VisitNotesFormSchema::stateFromRecord($booking?->visitRecord);
            })
            ->modalHeading(__('Complete visit'))
            ->modalDescription(__('Add optional notes, or leave everything blank and tap Complete.'))
            ->modalSubmitActionLabel(__('Complete'))
            ->extraModalFooterActions([
                Action::make('editVisitNotes')
                    ->label(__('Edit'))
                    ->color('gray')
                    ->visible(fn (): bool => (bool) $this->activeLiveSession?->currentBooking?->visitRecord?->hasClinicalContent())
                    ->action(function (): void {
                        $this->replaceMountedAction('completeVisit', ['forceForm' => true]);
                    }),
            ])
            ->action(function (
                array $data,
                LiveSessionService $liveSessionService,
                VisitRecordService $visitRecordService,
            ): void {
                // No announce here — nobody new has been called yet.
                CompleteBookingWithVisitNotes::completeCurrentSessionPatientWithoutAdvancing(
                    $data,
                    $liveSessionService,
                    $visitRecordService,
                    $this->activeLiveSession,
                );

                $this->forgetQueueState();
            });
    }

    /**
     * Play the same recorded “Number N” clip staff hear on the outdoor TV
     * (so Live Queue Control is not silent / not browser TTS).
     */
    private function dispatchCallAnnounce(): void
    {
        $tenant = tenant();
        if (! $tenant?->usesCallVoice()) {
            return;
        }

        $this->activeLiveSession?->refresh();
        $serial = $this->activeLiveSession?->currentBooking?->serial_number;
        if (! $serial) {
            return;
        }

        $this->dispatch('queue-called', serial: (int) $serial);
    }

    public function patientArrived()
    {
        if (!$this->activeLiveSession) return;
        
        app(LiveSessionService::class)->patientArrived($this->activeLiveSession);

        // No toast — the card badge flips to "Inside doctor chamber" already.
    }

    public function skipPatient()
    {
        if (!$this->activeLiveSession) return;

        $booking = $this->activeLiveSession->currentBooking;
        $name = $booking?->patient_name;
        $serial = $booking?->serial_number;

        app(LiveSessionService::class)->skipPatient($this->activeLiveSession);

        $skipped = $booking?->fresh();

        Notification::make()
            ->title($skipped?->status === 'no_show'
                ? __('#:serial :name marked no-show', ['serial' => $serial, 'name' => $name])
                : __('#:serial :name moved down the queue', ['serial' => $serial, 'name' => $name]))
            ->body($skipped?->status === 'no_show'
                ? __('Two missed calls. Use Reinstate on their row to put them back.')
                : __('They will be called again after #:after.', ['after' => (int) $skipped?->retry_queue_position - 1]))
            ->warning()
            ->send();

        $this->dispatchCallAnnounce();
    }

    public function reinstatePatient($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        app(LiveSessionService::class)->reinstatePatient($booking);
        Notification::make()->title('Patient Reinstated')->success()->send();
    }

    public function addMockPatients()
    {
        // Demo tooling only — never available outside local, and never mutates the real schedule.
        if (! app()->isLocal()) {
            Notification::make()
                ->title('Sample patients are only available in local development')
                ->danger()
                ->send();

            return;
        }

        if (! $this->selectedSessionId) {
            return;
        }

        $session = ScheduleSession::findOrFail($this->selectedSessionId);
        $today = Carbon::today();

        if ((int) $session->day_of_week !== $today->dayOfWeek) {
            Notification::make()
                ->title('This session does not run today')
                ->body('Pick a session scheduled for ' . $today->translatedFormat('l') . ', or change the session day in Schedules.')
                ->warning()
                ->send();

            return;
        }

        $bookingService = app(BookingService::class);
        $mockNames = ['ফাতেমা বেগম', 'মোহাম্মদ করিম', 'রশিদা আক্তার', 'আবদুল হাসান', 'নুসরাত জাহান', 'আমিনুল ইসলাম'];

        $added = 0;
        foreach ($mockNames as $index => $name) {
            try {
                $bookingService->createBookingForBookable(
                    $session,
                    $today->toDateString(),
                    $name,
                    '0170000000' . ($index + 1),
                    sendSms: false,
                );
                $added++;
            } catch (\Throwable $e) {
                // Ignore if duplicate or full
            }
        }

        Notification::make()
            ->title("Added {$added} Sample Patients")
            ->success()
            ->send();
    }

    // Action for Mark Late
    public function markLateAction(): Action
    {
        return Action::make('markLate')
            ->label('Mark Late')
            ->color('warning')
            ->icon('heroicon-o-clock')
            ->form([
                Select::make('delay_minutes')
                    ->label('Delay Duration')
                    ->options([
                        15 => '15 minutes',
                        30 => '30 minutes',
                        45 => '45 minutes',
                        60 => '1 hour',
                        90 => '1.5 hours',
                        120 => '2 hours',
                    ])
                    ->required(),
            ])
            ->action(function (array $data) {
                if (!$this->selectedSessionId) return;
                $scheduleSession = ScheduleSession::findOrFail($this->selectedSessionId);
                
                // create or update live session
                $liveSession = LiveSession::firstOrCreate([
                    'tenant_id' => tenant('id'),
                    'schedule_session_id' => $scheduleSession->id,
                    'session_date' => Carbon::today(),
                ], [
                    'status' => 'delayed',
                ]);
                
                app(LiveSessionService::class)->markDelay($liveSession, $data['delay_minutes']);
                
                Notification::make()->title('Session Delayed')->success()->send();
            })
            ->visible(fn () => $this->selectedSessionId && (!$this->activeLiveSession || $this->activeLiveSession->status === 'scheduled'));
    }

    // Action for Pause
    public function pauseSessionAction(): Action
    {
        return Action::make('pauseSession')
            ->label('Pause Session')
            ->color('gray')
            ->icon('heroicon-o-pause')
            ->form([
                TextInput::make('reason')
                    ->label('Reason (e.g. Prayer break)')
                    ->required(),
                Select::make('estimated_minutes')
                    ->label('Estimated Duration')
                    ->options([
                        10 => '10 minutes',
                        15 => '15 minutes',
                        20 => '20 minutes',
                        30 => '30 minutes',
                        45 => '45 minutes',
                        60 => '1 hour',
                    ])
                    ->required(),
            ])
            ->action(function (array $data) {
                if (!$this->activeLiveSession) return;
                app(LiveSessionService::class)->pauseSession(
                    $this->activeLiveSession, 
                    $data['reason'], 
                    $data['estimated_minutes']
                );
                Notification::make()->title('Session Paused')->warning()->send();
            })
            ->visible(fn () => $this->activeLiveSession && $this->activeLiveSession->status === 'active');
    }

    public function resumeSessionAction(): Action
    {
        return Action::make('resumeSession')
            ->label('Resume Session')
            ->color('success')
            ->icon('heroicon-o-play')
            ->action(function () {
                if (!$this->activeLiveSession) return;
                app(LiveSessionService::class)->resumeSession($this->activeLiveSession);
                Notification::make()->title('Session Resumed')->success()->send();
            })
            ->visible(fn () => $this->activeLiveSession && $this->activeLiveSession->status === 'paused');
    }

    public function markAbsentAction(): Action
    {
        return Action::make('markAbsent')
            ->label('Cancel Session (Doctor Absent)')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->modalDescription('Are you sure? This will cancel the session and all active patient bookings.')
            ->action(function () {
                if (!$this->selectedSessionId) return;
                $scheduleSession = ScheduleSession::findOrFail($this->selectedSessionId);
                
                $liveSession = LiveSession::firstOrCreate([
                    'tenant_id' => tenant('id'),
                    'schedule_session_id' => $scheduleSession->id,
                    'session_date' => Carbon::today(),
                ], [
                    'status' => 'cancelled',
                ]);
                
                app(LiveSessionService::class)->markAbsent($liveSession);
                
                Notification::make()->title('Session Cancelled')->success()->send();
            })
            ->visible(fn () => $this->selectedSessionId && (!$this->activeLiveSession || !in_array($this->activeLiveSession->status, ['completed', 'cancelled'])));
    }
}
