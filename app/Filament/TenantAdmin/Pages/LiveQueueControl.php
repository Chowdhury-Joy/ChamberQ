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
use Illuminate\Support\Facades\DB;
use App\Models\Booking;

class LiveQueueControl extends Page
{
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
        return [];
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
            ->label('Mark Absent')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->modalDescription('Are you sure? This will cancel all bookings and issue credits.')
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
