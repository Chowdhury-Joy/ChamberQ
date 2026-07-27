<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\ScheduleSession;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
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
use App\Services\BookingService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use App\Models\Booking;

class LiveQueueControl extends Page implements HasActions, HasTable
{
    use InteractsWithActions, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Live Queue Control';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.tenant-admin.pages.live-queue-control';

    public $selectedSessionId = null;

    public function mount()
    {
        // Try to auto-select an active session today
        $activeLiveSession = LiveSession::whereDate('session_date', Carbon::today())
            ->whereIn('status', ['active', 'paused', 'delayed'])
            ->first();

        if ($activeLiveSession) {
            $this->selectedSessionId = $activeLiveSession->schedule_session_id;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->markLateAction(),
            $this->pauseSessionAction(),
            $this->resumeSessionAction(),
            $this->markAbsentAction(),
            $this->endSessionAction(),
            
            \Filament\Actions\Action::make('newWalkIn')
                ->label(__('New Walk-In'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn () => $this->selectedSessionId !== null)
                ->schema([
                    \Filament\Forms\Components\TextInput::make('patient_name')
                        ->label(__('Patient name'))
                        ->extraInputAttributes(['name' => 'patient_name'])
                        ->autocomplete('name')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('patient_phone')
                        ->label(__('Phone number'))
                        ->extraInputAttributes(['name' => 'patient_phone'])
                        ->autocomplete('tel')
                        ->tel()
                        ->required()
                        ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
                        ->validationMessages([
                            'regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
                        ]),
                ])
                ->action(function (array $data, \App\Services\BookingService $bookingService) {
                    $bookable = \App\Models\ScheduleSession::findOrFail($this->selectedSessionId);

                    $bookingService->createBookingForBookable(
                        $bookable,
                        \Carbon\Carbon::today()->toDateString(),
                        $data['patient_name'],
                        $data['patient_phone']
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
            ->extraAttributes([
                'style' => 'background-color: #fef2f2 !important; border: 1px solid #dc2626 !important; color: #dc2626 !important;',
                'class' => 'font-bold [&_svg]:!text-red-600 [&_svg]:!fill-red-600'
            ])
            ->action(function () {
                if (!$this->activeLiveSession) return;
                app(LiveSessionService::class)->endSession($this->activeLiveSession);
                Notification::make()->title('Session Ended')->success()->send();
            })
            ->visible(fn () => $this->activeLiveSession && in_array($this->activeLiveSession->status, ['active', 'paused']));
    }

    public function getSessionsProperty()
    {
        return ScheduleSession::with('chamber')
            ->get()
            ->mapWithKeys(function ($session) {
                return [$session->id => $session->chamber->name . ' - ' . $session->start_time . ' to ' . $session->end_time];
            });
    }

    public function getActiveLiveSessionProperty()
    {
        if (!$this->selectedSessionId) return null;

        return LiveSession::where('schedule_session_id', $this->selectedSessionId)
            ->whereDate('session_date', Carbon::today())
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
                    ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
                    ->orderBy('serial_number')
            )
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
                    ->label('Retry After')
                    ->formatStateUsing(fn ($state) => $state ? "#" . ($state - 1) : null)
                    ->color('warning')
                    ->visible(fn () => $this->bookings->whereNotNull('retry_queue_position')->count() > 0),
            ])
            ->actions([
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
    }

    public function nextPatient()
    {
        if (!$this->activeLiveSession) return;
        
        app(LiveSessionService::class)->completeCurrentPatient($this->activeLiveSession);
        
        Notification::make()->title('Called Next Patient')->success()->send();
    }

    public function patientArrived()
    {
        if (!$this->activeLiveSession) return;
        
        app(LiveSessionService::class)->patientArrived($this->activeLiveSession);
        
        Notification::make()->title('Patient marked as arrived')->success()->send();
    }

    public function skipPatient()
    {
        if (!$this->activeLiveSession) return;
        
        app(LiveSessionService::class)->skipPatient($this->activeLiveSession);
        
        Notification::make()->title('Patient Skipped')->warning()->send();
    }

    public function endSession()
    {
        if (!$this->activeLiveSession) return;
        
        app(LiveSessionService::class)->endSession($this->activeLiveSession);
        
        Notification::make()->title('Session Ended')->success()->send();
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
                    '0170000000' . ($index + 1)
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
