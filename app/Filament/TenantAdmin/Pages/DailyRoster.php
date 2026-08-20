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
use App\Filament\TenantAdmin\Support\RosterRecordActions;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LiveSession;
use Carbon\Carbon;
use App\Exceptions\BookingUnavailableException;
use App\Services\LiveSessionService;
use App\Services\SittingPrompt;
use Filament\Notifications\Notification;
use App\Models\ScheduleSession;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

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

    /** Day the table lists. Walk-in / Mark Late / Live Queue stay on today. */
    public string $rosterDate = '';

    public function mount(): void
    {
        $this->rosterDate = Carbon::today()->toDateString();
    }

    public function updatedRosterDate(): void
    {
        $this->rosterDate = $this->rosterDateString();
        $this->resetTable();
    }

    public function jumpToToday(): void
    {
        $this->rosterDate = Carbon::today()->toDateString();
        $this->resetTable();
    }

    public function rosterDateString(): string
    {
        $raw = trim($this->rosterDate);

        try {
            return filled($raw)
                ? Carbon::parse($raw)->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable) {
            return Carbon::today()->toDateString();
        }
    }

    public function isViewingToday(): bool
    {
        return $this->rosterDateString() === Carbon::today()->toDateString();
    }

    public function rosterDateLabel(): string
    {
        return Carbon::parse($this->rosterDateString())->translatedFormat('l, j F Y');
    }

    public function getSittingPromptsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->isViewingToday()) {
            return collect();
        }

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
        return $table
            ->query(function () {
                $user = auth()->user();

                $bookingQuery = Booking::query()
                    ->where('booking_date', $this->rosterDateString())
                    ->with(['visitRecord.prescription', 'cashEntry.feeCatalogItem', 'feeCatalogItem', 'bookable.doctor', 'bookable', 'labTests', 'procedureBookings.bookable', 'patient'])
                    ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
                    ->orderBy('serial_number');

                if ($user instanceof \App\Models\User) {
                    StaffDeskScope::constrainBookings($bookingQuery, $user);
                }

                return $bookingQuery;
            })
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

                        $kind = $record->bookable?->kindLabel() ?? '—';
                        $procedure = $record->feeCatalogItem?->label;

                        return filled($procedure) ? $kind.' · '.$procedure : $kind;
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
                TextColumn::make('remarks')
                    ->label(__('Remarks'))
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
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
                    ->visible(fn (): bool => $this->isViewingToday()
                        && (auth()->user() instanceof \App\Models\User
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
                        $this->delayedNotifyBookingIds = ($doctor?->wantsStaffTap(Doctor::NOTIFY_DOCTOR_LATE) ?? false)
                            ? $bookings->pluck('id')->all()
                            : [];

                        Notification::make()->title(__('Session Delayed'))->success()->send();
                    }),

                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary')
                    ->visible(fn (): bool => $this->isViewingToday()
                        && (auth()->user()?->canAccessLiveQueueControl() ?? false)
                        && static::markableSessionOptions() === []),

                ActionGroup::make([
                    Action::make('manageQueueMore')
                        ->label('Manage Live Queue')
                        ->icon('heroicon-o-queue-list')
                        ->url(LiveQueueControl::getUrl())
                        ->visible(fn (): bool => $this->isViewingToday()
                            && (auth()->user()?->canAccessLiveQueueControl() ?? false)
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
                                'delayMinutes' => $minutes,
                                'messages' => $bookings->mapWithKeys(fn (Booking $booking) => [
                                    $booking->id => __('Hello :name, the doctor is running :minutes minutes late. Your serial is :serial.', [
                                        'name' => $booking->patient_name,
                                        'minutes' => $minutes,
                                        'serial' => $booking->serial_number,
                                    ]),
                                ])->all(),
                            ]);
                        })
                        ->visible(fn (): bool => $this->isViewingToday()
                            && $this->delayedNotifyBookingIds !== []),
                ])
                    ->label(__('More'))
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->button()
                    ->visible(fn (): bool => $this->isViewingToday()
                        && ($this->delayedNotifyBookingIds !== []
                            || ((auth()->user()?->canAccessLiveQueueControl() ?? false)
                                && static::markableSessionOptions() !== []))),

                Action::make('newWalkIn')
                    ->label('New Walk-In')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->isViewingToday())
                    ->schema(StaffBookingForm::walkInComponents())
                    ->fillForm([
                        'visit_type' => StaffBookingForm::TYPE_USUAL,
                        'share_clinical_history' => true,
                    ])
                    ->action(function (array $data) {
                        try {
                            StaffBookingForm::createFromState(
                                $data,
                                today()->toDateString(),
                                allowOverflow: true,
                                allowEndedToday: true,
                                sendSms: true,
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

        if (! $doctor?->wantsAutoSms(Doctor::NOTIFY_DOCTOR_LATE)) {
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
