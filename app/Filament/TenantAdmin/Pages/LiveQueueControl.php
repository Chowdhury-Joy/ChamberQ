<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Concerns\AppliesVisitNotesDrafts;
use App\Filament\TenantAdmin\Support\CollectFeeAction;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Filament\TenantAdmin\Support\CompleteBookingWithVisitNotes;
use App\Filament\TenantAdmin\Support\DeskActionLayout;
use App\Filament\TenantAdmin\Support\QueueRecordActions;
use App\Filament\TenantAdmin\Support\StationsHandoffForm;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\ScheduleSession;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\StaffDeskScope;
use App\Services\LiveSessionService;
use App\Services\SittingPrompt;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Exceptions\BookingUnavailableException;
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

    /**
     * Bookings the last "End session" cancelled, so the WhatsApp hand-off
     * survives the re-render. Public so Livewire keeps it across requests.
     *
     * @var list<string>
     */
    public array $cancelledByEndSessionIds = [];

    /**
     * Waiting bookings after the last "Mark Late", for optional WhatsApp
     * hand-off when that doctor's doctor_late WhatsApp preference is on.
     *
     * @var list<string>
     */
    public array $delayedNotifyBookingIds = [];

    /** Minutes from the last Mark Late, used in WhatsApp copy. */
    public int $delayedNotifyMinutes = 0;
    public function mount()
    {
        $user = auth()->user();

        $candidates = LiveSession::with('scheduleSession')
            ->where('session_date', Carbon::today()->toDateString())
            ->whereIn('status', ['active', 'paused', 'delayed'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $activeLiveSession = $candidates->first(function (LiveSession $live) use ($user): bool {
            if (! $live->scheduleSession) {
                return false;
            }

            return ! ($user instanceof User) || StaffDeskScope::sessionIsVisible($user, $live->scheduleSession);
        });

        if ($activeLiveSession) {
            $this->selectedSessionId = $activeLiveSession->schedule_session_id;

            return;
        }

        if ($this->sessions->count() === 1) {
            $this->selectedSessionId = $this->sessions->keys()->first();
        }
    }

    public function getSittingPromptsProperty(): \Illuminate\Support\Collection
    {
        return app(SittingPrompt::class)->promptsForToday();
    }

    public function getSittingPromptForSelectionProperty(): ?array
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        return $this->sittingPrompts
            ->firstWhere('schedule_session_id', (int) $this->selectedSessionId);
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
                ->label('Session actions')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button()
                ->visible(fn () => $this->selectedSessionId !== null),

            // Stands alone, not inside the menu: these patients are already
            // cancelled and still expecting to be seen today.
            $this->notifyCancelledAction(),
            $this->notifyDelayedAction(),

            \Filament\Actions\Action::make('newWalkIn')
                ->label('New Walk-In')
                ->icon('heroicon-o-user-plus')
                ->visible(fn () => $this->selectedSessionId !== null)
                ->schema(fn (): array => StaffBookingForm::liveQueueWalkInComponents(
                    ScheduleSession::find($this->selectedSessionId),
                ))
                ->fillForm(function (): array {
                    $session = ScheduleSession::find($this->selectedSessionId);
                    $types = StaffBookingForm::visitTypeOptionsForSitting($session);

                    return [
                        'visit_type' => array_key_first($types) ?? StaffBookingForm::TYPE_USUAL,
                        'lab_type' => $session?->kind === ScheduleSession::KIND_MSK
                            ? StaffBookingForm::LAB_MSK
                            : null,
                        'share_clinical_history' => true,
                    ];
                })
                ->action(function (array $data) {
                    $bookable = ScheduleSession::findOrFail($this->selectedSessionId);

                    try {
                        StaffBookingForm::createFromState(
                            $data,
                            Carbon::today()->toDateString(),
                            allowOverflow: true,
                            allowEndedToday: true,
                            sendSms: true,
                            forcedBookable: $bookable,
                        );
                    } catch (BookingUnavailableException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                        $this->halt();
                    }
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
            // Name the cost before they commit. "All remaining patients will be
            // cancelled" does not tell a queue runner whether that is nobody or
            // nine people still sitting in the waiting room.
            ->modalDescription(function (): string {
                $pending = $this->bookingsEndSessionWouldCancel();

                if ($pending->isEmpty()) {
                    return __('Nobody is still waiting, so ending the session will not cancel anyone.');
                }

                return __('This cancels :count patient(s) who are still in the queue: :serials. You will get WhatsApp links to tell them.', [
                    'count' => $pending->count(),
                    'serials' => $pending
                        ->map(fn (Booking $booking) => '#'.$booking->serial_number.' '.$booking->patient_name)
                        ->implode(', '),
                ]);
            })
            ->modalSubmitActionLabel('End session')
            ->action(function () {
                if (!$this->activeLiveSession) return;
                $catchUpCount = 0;
                if (auth()->user()?->canRecordVisitNotes()) {
                    $catchUpCount = app(VisitRecordService::class)->countCompletedBookingsWithoutNotesToday(
                        $this->activeLiveSession,
                    );
                }

                $cancelled = app(LiveSessionService::class)->endSession($this->activeLiveSession);

                // Held on the component so "Tell cancelled patients" stays
                // available after the page re-renders. These people are
                // expecting to be seen today; the button is the only thing
                // standing between them and a wasted trip.
                $this->cancelledByEndSessionIds = $cancelled->pluck('id')->all();
                $this->forgetQueueState();

                if ($cancelled->isNotEmpty()) {
                    Notification::make()
                        ->title(__('Session ended — :count patient(s) cancelled', ['count' => $cancelled->count()]))
                        ->body(__('Use "Tell cancelled patients" to send each of them a WhatsApp message.'))
                        ->warning()
                        ->persistent()
                        ->send();
                } elseif ($catchUpCount > 0 && auth()->user()?->canRecordVisitNotes()) {
                    Notification::make()
                        ->title(__('Session ended'))
                        ->body(__(':count patients today without notes — tap Fill in now on this page while the evening is fresh.', [
                            'count' => $catchUpCount,
                        ]))
                        ->warning()
                        ->duration(12000)
                        ->send();
                } else {
                    Notification::make()->title(__('Session Ended'))->success()->send();
                }

                if ($cancelled->isNotEmpty() && $catchUpCount > 0 && auth()->user()?->canRecordVisitNotes()) {
                    Notification::make()
                        ->title(__(':count patients today without notes', ['count' => $catchUpCount]))
                        ->body(__('Tap Fill in now on this page while the evening is fresh.'))
                        ->warning()
                        ->duration(12000)
                        ->send();
                }
            })
            ->visible(fn () => $this->activeLiveSession && in_array($this->activeLiveSession->status, ['active', 'paused']));
    }

    /**
     * Patients ending the session right now would turn away.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    public function bookingsEndSessionWouldCancel()
    {
        if (! $this->activeLiveSession) {
            return collect();
        }

        return app(LiveSessionService::class)
            ->bookingsEndSessionWouldCancel($this->activeLiveSession);
    }

    /**
     * WhatsApp hand-off for the patients the last "End session" cancelled.
     *
     * Mirrors vacation mode: nothing is sent automatically, staff tap one link
     * per patient. Without this, ending a session early silently binned
     * everyone still waiting and the first they heard of it was arriving.
     */
    public function notifyCancelledAction(): Action
    {
        return Action::make('notifyCancelled')
            ->label('Tell cancelled patients')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('warning')
            ->modalHeading('Let these patients know')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->modalContent(function (): \Illuminate\Contracts\View\View {
                $bookings = Booking::whereIn('id', $this->cancelledByEndSessionIds)
                    ->orderBy('serial_number')
                    ->get();

                return view('filament.tenant-admin.slot-block-notify', [
                    'bookings' => $bookings,
                    'stage' => \App\Models\Doctor::NOTIFY_CANCELLATION,
                    'messages' => $bookings->mapWithKeys(fn (Booking $booking) => [
                        $booking->id => __("Hello :name, sorry — today's session ended before your serial :serial. Your appointment has been cancelled. Please contact us to rebook.", [
                            'name' => $booking->patient_name,
                            'serial' => $booking->serial_number,
                        ]),
                    ])->all(),
                ]);
            })
            ->visible(fn (): bool => $this->cancelledByEndSessionIds !== []);
    }

    /**
     * WhatsApp hand-off after Mark Late when the doctor enabled late WhatsApp.
     */
    public function notifyDelayedAction(): Action
    {
        return Action::make('notifyDelayed')
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
                    'stage' => \App\Models\Doctor::NOTIFY_DOCTOR_LATE,
                    'messages' => $bookings->mapWithKeys(fn (Booking $booking) => [
                        $booking->id => __('Hello :name, the doctor is running :minutes minutes late. Your serial is :serial.', [
                            'name' => $booking->patient_name,
                            'minutes' => $minutes,
                            'serial' => $booking->serial_number,
                        ]),
                    ])->all(),
                ]);
            })
            ->visible(fn (): bool => $this->delayedNotifyBookingIds !== []);
    }

    public function getSessionsProperty()
    {
        $today = Carbon::today()->dayOfWeek;

        $query = ScheduleSession::with('chamber')
            ->where('day_of_week', $today)
            ->orderBy('start_time');

        $user = auth()->user();
        if ($user instanceof User) {
            StaffDeskScope::constrainScheduleSessions($query, $user);
        }

        return $query
            ->get()
            ->mapWithKeys(function ($session) {
                $chamber = $session->chamber?->name ?? 'Chamber';
                $label = $chamber.' — '.$session->session_name;
                if (tenant()?->hasStations() && filled($session->kind)) {
                    $label .= ' · '.($session->kindLabel() ?? $session->kind);
                }
                $label .= ' ('.$session->start_time.'–'.$session->end_time.')';

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
        unset(
            $this->activeLiveSession,
            $this->bookings,
            $this->queueStats,
            $this->catchUpCount,
            $this->catchUpBookings,
            $this->sittingPrompts,
            $this->sittingPromptForSelection,
        );
    }

    public function getActiveLiveSessionProperty()
    {
        if (!$this->selectedSessionId) return null;

        return LiveSession::where('schedule_session_id', $this->selectedSessionId)
            ->where('session_date', Carbon::today()->toDateString())
            ->with([
                'currentBooking.visitRecord.prescription.items',
                'currentBooking.bookable',
                'currentBooking.procedureBookings.bookable',
                'currentBooking.relatedBooking.bookable',
            ])
            ->first();
    }

    public function getBookingsProperty()
    {
        if (!$this->selectedSessionId) return collect();

        return Booking::where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $this->selectedSessionId)
            ->where('booking_date', Carbon::today()->toDateString())
            ->with(['bookable', 'procedureBookings.bookable', 'relatedBooking.bookable'])
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
                              ->where('booking_date', Carbon::today()->toDateString());
                    }, function ($query) {
                        $query->whereRaw('1 = 0');
                    })
                    ->with(['patient', 'visitRecord', 'cashEntry', 'bookable', 'procedureBookings.bookable', 'relatedBooking.bookable'])
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
                    ->label(__('Serial'))
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('patient_name')
                    ->label(__('Patient Details'))
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
                        if ($state === 'skipped') {
                            return __('Skipped (:count/2)', ['count' => $record->skip_count]);
                        }

                        return match ($state) {
                            'waiting' => __('Waiting'),
                            'called' => __('Called'),
                            'in_chamber' => __('In chamber'),
                            'completed' => __('Completed'),
                            'cancelled' => __('Cancelled'),
                            'no_show' => __('No-show'),
                            default => str_replace('_', ' ', Str::title((string) $state)),
                        };
                    }),
                TextColumn::make('retry_queue_position')
                    ->label(__('Back in queue'))
                    ->formatStateUsing(fn ($state, Booking $record) => $state && $record->status === 'skipped'
                        ? __('After #:serial', ['serial' => $state - 1])
                        : null)
                    ->color('warning')
                    ->visible(fn () => $this->bookings
                        ->where('status', 'skipped')
                        ->whereNotNull('retry_queue_position')
                        ->count() > 0),
            ])
            ->recordActions(QueueRecordActions::compact(
                fn (Booking $record) => $this->callPatientNow($record->id),
                fn (Booking $record) => $this->reinstatePatient($record->id),
            ))
            ->poll('3s')
            ->paginated(false);
    }

    public function startSession()
    {
        $this->mountAction('startSession');
    }

    public function doStartSession(): void
    {
        if (! $this->selectedSessionId) {
            return;
        }

        $scheduleSession = ScheduleSession::findOrFail($this->selectedSessionId);

        app(LiveSessionService::class)->startSession($scheduleSession);

        Notification::make()->title(__('Session Started'))->success()->send();
        $this->forgetQueueState();
        $this->dispatchCallAnnounce();
    }

    public function startSessionAction(): Action
    {
        return Action::make('startSession')
            ->label('Start live session')
            ->color('success')
            ->icon('heroicon-m-play')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cancel')
            ->modalHeading(fn (): string => match ($this->startModalKind()) {
                'early_during_delay' => __('Start before the announced time?'),
                default => __('Start after sitting time?'),
            })
            ->modalDescription(fn (): ?string => $this->startModalDescription())
            ->extraModalFooterActions(fn (): array => $this->startModalFooterActions())
            ->action(fn () => $this->doStartSession());
    }

    protected function startModalKind(): ?string
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        $scheduleSession = ScheduleSession::find($this->selectedSessionId);

        if (! $scheduleSession) {
            return null;
        }

        return app(SittingPrompt::class)->startModalKind(
            $scheduleSession,
            $this->activeLiveSession,
        );
    }

    protected function startModalDescription(): ?string
    {
        return match ($this->startModalKind()) {
            'early_during_delay' => $this->sittingPromptForSelection['message'] ?? null,
            'late_without_notice' => __('Patients have not been told the doctor is late. Mark Late to slide the ticket, or just start and the clock will follow when you actually begin.'),
            default => null,
        };
    }

    /**
     * @return list<Action>
     */
    protected function startModalFooterActions(): array
    {
        $kind = $this->startModalKind();

        if ($kind === null) {
            return [];
        }

        if ($kind === 'early_during_delay') {
            $announcedAt = $this->sittingPromptForSelection['announced_at'] ?? null;

            return [
                Action::make('startNowDuringDelay')
                    ->label('Start now')
                    ->color('success')
                    ->action(function (): void {
                        $this->unmountAction();
                        $this->doStartSession();
                    }),
                Action::make('waitUntilAnnounced')
                    ->label($announcedAt
                        ? 'Wait until '.$announcedAt->format('g:i a')
                        : 'Wait')
                    ->color('gray')
                    ->action(fn () => $this->unmountAction()),
            ];
        }

        $suggested = $this->sittingPromptForSelection['suggested_delay_minutes']
            ?? app(SittingPrompt::class)->suggestedDelayMinutes(
                (int) ($this->sittingPromptForSelection['minutes_late'] ?? 15),
            );

        return [
            Action::make('markLateFromStart')
                ->label('Mark Late ('.$suggested.' min)')
                ->color('warning')
                ->action(function () use ($suggested): void {
                    $this->unmountAction();
                    $this->mountAction('markLate', ['delay_minutes' => $suggested]);
                }),
            Action::make('justStartLate')
                ->label('Just start')
                ->color('success')
                ->action(function (): void {
                    $this->unmountAction();
                    $this->doStartSession();
                }),
        ];
    }

    public function mountStartSessionOrRun(): void
    {
        if ($this->startModalKind() === null) {
            $this->doStartSession();

            return;
        }

        $this->mountAction('startSession');
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

        Notification::make()->title(__('Visit completed'))->success()->send();
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
                ->title(__('Could not call that patient'))
                ->body(__('Finish the patient currently in the chamber first.'))
                ->warning()
                ->send();

            return;
        }

        $this->dispatchCallAnnounce();
    }

    public function bookCurrentInterventionAction(): Action
    {
        return StationsHandoffForm::bookAction(
            Action::make('bookCurrentIntervention'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        );
    }

    public function moveCurrentInterventionAction(): Action
    {
        return StationsHandoffForm::moveAction(
            Action::make('moveCurrentIntervention'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        );
    }

    public function sendCurrentToCounselingAction(): Action
    {
        return StationsHandoffForm::sendToCounselingAction(
            Action::make('sendCurrentToCounseling'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        );
    }

    public function sendCurrentToMskAction(): Action
    {
        return StationsHandoffForm::sendToMskAction(
            Action::make('sendCurrentToMsk'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        );
    }

    public function sendCurrentToReportAction(): Action
    {
        return StationsHandoffForm::sendToReportAction(
            Action::make('sendCurrentToReport'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        );
    }

    public function collectCurrentFeeAction(): Action
    {
        return CollectFeeAction::make(
            Action::make('collectCurrentFee'),
            fn (): ?Booking => $this->activeLiveSession?->currentBooking,
        )->visible(function (): bool {
            $booking = $this->activeLiveSession?->currentBooking;

            return $booking instanceof Booking
                && DeskActionLayout::feeIsPrimaryOnCard($booking);
        });
    }

    public function completeVisitAction(): Action
    {
        return VisitNotesFormSchema::configureModal(Action::make('completeVisit'))
            ->label('Complete visit')
            ->color('success')
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

                return VisitNotesFormSchema::components($patient, $lastVisit, $booking);
            })
            ->fillForm(function (): array {
                $booking = $this->activeLiveSession?->currentBooking;

                return VisitNotesFormSchema::stateFromRecord($booking?->visitRecord);
            })
            ->modalHeading('Complete visit')
            ->modalDescription(__('Add optional notes, or leave everything blank and tap Complete.'))
            ->modalSubmitActionLabel('Complete')
            ->extraModalFooterActions([
                Action::make('editVisitNotes')
                    ->label('Edit')
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
     * Play the same recorded “Number N” clip staff hear on the outdoor TV,
     * then say the patient name (browser TTS — try-it for names only).
     */
    private function dispatchCallAnnounce(): void
    {
        $tenant = tenant();
        if (! $tenant?->usesCallVoice()) {
            return;
        }

        $this->activeLiveSession?->refresh();
        $booking = $this->activeLiveSession?->currentBooking;
        $serial = $booking?->serial_number;
        if (! $serial) {
            return;
        }

        $this->dispatch('queue-called',
            serial: (int) $serial,
            name: $booking?->patient_name,
        );
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
        Notification::make()->title(__('Patient Reinstated'))->success()->send();
    }

    public function addMockPatients()
    {
        // Demo tooling only — never available outside local, and never mutates the real schedule.
        if (! app()->isLocal()) {
            Notification::make()
                ->title(__('Sample patients are only available in local development'))
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
                ->title(__('This session does not run today'))
                ->body(__('Pick a session scheduled for :day, or change the session day in Schedules.', [
                    'day' => $today->translatedFormat('l'),
                ]))
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
            ->title(__('Added :count Sample Patients', ['count' => $added]))
            ->success()
            ->send();
    }

    // Action for Mark Late
    /**
     * What "Mark Late" is about to send, and what it will cost.
     *
     * Null when this doctor has late SMS switched off, in which case marking
     * late spends nothing and there is no warning worth showing.
     */
    public function markLateCostWarning(): ?string
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        $doctor = ScheduleSession::with('doctor')->find($this->selectedSessionId)?->doctor;

        if (! $doctor?->wantsSms(\App\Models\Doctor::NOTIFY_DOCTOR_LATE)) {
            return null;
        }

        $waiting = $this->bookings
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

    public function markLateAction(): Action
    {
        $currentDelay = (int) ($this->activeLiveSession?->delay_minutes ?? 0);
        $isExtending = $this->activeLiveSession?->status === 'delayed';

        return Action::make('markLate')
            ->label($isExtending ? 'Add time' : 'Mark Late')
            ->color('warning')
            ->icon('heroicon-o-clock')
            ->fillForm(fn (array $arguments): array => [
                'delay_minutes' => $arguments['delay_minutes']
                    ?? ($isExtending
                        ? array_key_first(app(SittingPrompt::class)->delayOptionsFor($currentDelay))
                        : null),
            ])
            ->form([
                Select::make('delay_minutes')
                    ->label($isExtending ? __('Additional delay (total)') : __('Delay Duration'))
                    ->options(fn (): array => app(SittingPrompt::class)->delayOptionsFor($currentDelay))
                    ->required()
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($currentDelay): void {
                        if ($currentDelay > 0 && (int) $value <= $currentDelay) {
                            $fail(__('Choose a longer delay than the :minutes minutes already announced.', [
                                'minutes' => $currentDelay,
                            ]));
                        }
                    }),
            ])
            // Say what this costs before it is spent. End Session already names
            // the patients it is about to cancel; this quietly texted everyone
            // waiting and spent a credit each with no mention of either.
            ->modalDescription(fn (): ?string => $this->markLateCostWarning())
            ->action(function (array $data) {
                if (!$this->selectedSessionId) return;
                $scheduleSession = ScheduleSession::with('doctor')->findOrFail($this->selectedSessionId);
                
                // create or update live session.
                // toDateString(), not the Carbon: this array is the WHERE
                // clause as well as the insert payload, and a Carbon binds as
                // 'Y-m-d H:i:s' — which never matches the date-only column, so
                // the lookup misses an existing row and the insert then trips
                // the (tenant_id, schedule_session_id, session_date) unique
                // index. Same rule as LiveSessionService::startSession().
                $liveSession = LiveSession::firstOrCreate([
                    'tenant_id' => tenant('id'),
                    'schedule_session_id' => $scheduleSession->id,
                    'session_date' => Carbon::today()->toDateString(),
                ], [
                    'status' => 'delayed',
                ]);
                
                $bookings = app(LiveSessionService::class)->markDelay($liveSession, $data['delay_minutes']);

                $this->delayedNotifyMinutes = (int) $data['delay_minutes'];
                $doctor = $scheduleSession->doctor;
                $this->delayedNotifyBookingIds = ($doctor?->wantsWhatsapp(\App\Models\Doctor::NOTIFY_DOCTOR_LATE) ?? false)
                    ? $bookings->pluck('id')->all()
                    : [];
                
                $this->forgetQueueState();
                Notification::make()->title(__('Session Delayed'))->success()->send();
            })
            ->visible(fn () => $this->selectedSessionId
                && (! $this->activeLiveSession
                    || in_array($this->activeLiveSession->status, ['scheduled', 'delayed'], true)));
    }

    // Action for Pause
    public function pauseSessionAction(): Action
    {
        return Action::make('pauseSession')
            ->label('Doctor stepped out')
            ->color('gray')
            ->icon('heroicon-o-pause')
            ->modalDescription(__('Tickets keep their place. Call next is blocked until the doctor is back.'))
            ->form([
                TextInput::make('reason')
                    ->label(__('Reason (e.g. Prayer break)'))
                    ->required(),
                Select::make('estimated_minutes')
                    ->label(__('Estimated Duration'))
                    ->options([
                        10 => __('10 minutes'),
                        15 => __('15 minutes'),
                        20 => __('20 minutes'),
                        30 => __('30 minutes'),
                        45 => __('45 minutes'),
                        60 => __('1 hour'),
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
                Notification::make()->title(__('Doctor stepped out'))->warning()->send();
            })
            ->visible(fn () => $this->activeLiveSession && $this->activeLiveSession->status === 'active');
    }

    public function resumeSessionAction(): Action
    {
        return Action::make('resumeSession')
            ->label("He's back")
            ->color('success')
            ->icon('heroicon-o-play')
            ->action(function () {
                if (!$this->activeLiveSession) return;
                app(LiveSessionService::class)->resumeSession($this->activeLiveSession);
                Notification::make()->title(__('Session resumed'))->success()->send();
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
            // Name the cost before they commit, exactly as End Session does.
            // "all active patient bookings" does not tell a queue runner
            // whether that is nobody or nine people already on their way.
            ->modalDescription(function (): string {
                $pending = $this->bookingsEndSessionWouldCancel();

                if ($pending->isEmpty()) {
                    return __('Nobody is still waiting, so cancelling the session will not turn anyone away.');
                }

                return __('This cancels :count patient(s) still in the queue: :serials. You will get WhatsApp links to tell them.', [
                    'count' => $pending->count(),
                    'serials' => $pending
                        ->map(fn (Booking $booking) => '#'.$booking->serial_number.' '.$booking->patient_name)
                        ->implode(', '),
                ]);
            })
            ->action(function () {
                if (!$this->selectedSessionId) return;
                $scheduleSession = ScheduleSession::findOrFail($this->selectedSessionId);

                // toDateString(), not the Carbon — see markLateAction() above.
                $liveSession = LiveSession::firstOrCreate([
                    'tenant_id' => tenant('id'),
                    'schedule_session_id' => $scheduleSession->id,
                    'session_date' => Carbon::today()->toDateString(),
                ], [
                    'status' => 'cancelled',
                ]);

                $cancelled = app(LiveSessionService::class)->markAbsent($liveSession);

                // Same hand-off as End Session. The doctor is not coming, so
                // every one of these people would otherwise make the trip for
                // nothing — this is the path where telling them matters most.
                $this->cancelledByEndSessionIds = $cancelled->pluck('id')->all();
                $this->forgetQueueState();

                if ($cancelled->isNotEmpty()) {
                    Notification::make()
                        ->title(__('Session cancelled — :count patient(s) turned away', ['count' => $cancelled->count()]))
                        ->body(__('Use "Tell cancelled patients" to send each of them a WhatsApp message.'))
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title(__('Session Cancelled'))->success()->send();
            })
            ->visible(fn () => $this->selectedSessionId && (!$this->activeLiveSession || !in_array($this->activeLiveSession->status, ['completed', 'cancelled'])));
    }

    public function getCatchUpCountProperty(): int
    {
        if (! auth()->user()?->canRecordVisitNotes()) {
            return 0;
        }

        return app(VisitRecordService::class)->countCompletedBookingsWithoutNotesToday(
            $this->activeLiveSession,
        );
    }

    public function getCatchUpBookingsProperty(): \Illuminate\Support\Collection
    {
        return app(VisitRecordService::class)->completedBookingsWithoutNotesToday(
            $this->activeLiveSession,
        );
    }

    public function catchUpNotesAction(): Action
    {
        return Action::make('catchUpNotes')
            ->label('Fill in notes ('.$this->catchUpCount.')')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->modalHeading('Patients without notes today')
            ->modalDescription(__('Tap a patient to add optional visit notes. Nothing is required.'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                'filament.tenant-admin.components.catch-up-notes-list',
                ['bookings' => $this->catchUpBookings],
            ));
    }

    public function catchUpBookingAction(): Action
    {
        return VisitNotesFormSchema::configureModal(Action::make('catchUpBooking'))
            ->label('Add notes')
            ->form(function (Action $action): array {
                $bookingId = $action->getArguments()['bookingId'] ?? null;
                $booking = $bookingId ? Booking::with('patient')->find($bookingId) : null;

                return VisitNotesFormSchema::components($booking?->patient, null, $booking);
            })
            ->modalHeading('Add visit notes')
            ->modalDescription(__('All fields optional — voice, photo, diagnosis, or prescription.'))
            ->modalSubmitActionLabel('Save notes')
            ->action(function (
                array $data,
                array $arguments,
                VisitRecordService $visitRecordService,
            ): void {
                $bookingId = $arguments['bookingId'] ?? null;
                $booking = $bookingId ? Booking::find($bookingId) : null;
                $user = auth()->user();

                if (! $booking || ! $user?->canRecordVisitNotes()) {
                    return;
                }

                if ($visitRecordService->submissionHasContent($data)) {
                    $visitRecordService->saveForCompletedBooking($booking, $user, $data);
                }

                $this->forgetQueueState();

                Notification::make()
                    ->title(__('Notes saved'))
                    ->success()
                    ->send();
            });
    }
}
