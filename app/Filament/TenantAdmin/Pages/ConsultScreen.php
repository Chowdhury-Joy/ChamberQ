<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Patient;
use App\Models\VisitRecord;
use App\Filament\TenantAdmin\Support\CompleteBookingWithVisitNotes;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Services\LiveSessionService;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ConsultScreen extends Page implements HasActions
{
    use InteractsWithActions;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Consult Screen';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Consult Screen';

    protected string $view = 'filament.tenant-admin.pages.consult-screen';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canViewConsultScreen() ?? false;
    }

    public function getRunningLiveSessionProperty(): ?LiveSession
    {
        return LiveSession::query()
            ->whereDate('session_date', Carbon::today())
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->with(['currentBooking.patient', 'scheduleSession.doctor', 'scheduleSession.chamber'])
            ->first();
    }

    public function getCurrentBookingProperty(): ?Booking
    {
        return $this->runningLiveSession?->currentBooking;
    }

    public function getCurrentPatientProperty(): ?Patient
    {
        $booking = $this->currentBooking;

        if (! $booking) {
            return null;
        }

        if ($booking->relationLoaded('patient') && $booking->patient) {
            return $booking->patient;
        }

        if ($booking->patient_id) {
            return Patient::find($booking->patient_id);
        }

        return null;
    }

    public function getVisitHistoryProperty(): \Illuminate\Support\Collection
    {
        $patient = $this->currentPatient;

        if (! $patient) {
            return collect();
        }

        $query = $patient->bookings()
            ->where('status', 'completed')
            ->with(['visitRecord.condition', 'visitRecord.prescription'])
            ->orderByDesc('booking_date')
            ->orderByDesc('completed_at');

        if (tenant()?->isClinic()) {
            $query->with([
                'bookable' => function ($morphTo) {
                    $morphTo->morphWith([
                        ScheduleSession::class => ['doctor'],
                        LabCollectionSlot::class => ['chamber'],
                    ]);
                },
            ]);
        }

        return $query->limit(20)->get();
    }

    public function getLastVisitRecordProperty(): ?VisitRecord
    {
        $patient = $this->currentPatient;

        if (! $patient) {
            return null;
        }

        return app(VisitRecordService::class)->lastRecordedVisitForPatient(
            $patient,
            $this->currentBooking?->id,
        );
    }

    public function getCatchUpCountProperty(): int
    {
        if (! auth()->user()?->canRecordVisitNotes()) {
            return 0;
        }

        return app(VisitRecordService::class)->countCompletedBookingsWithoutNotesToday(
            $this->runningLiveSession,
        );
    }

    public function getCatchUpBookingsProperty(): \Illuminate\Support\Collection
    {
        return app(VisitRecordService::class)->completedBookingsWithoutNotesToday(
            $this->runningLiveSession,
        );
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (auth()->user()?->canRecordVisitNotes() && $this->catchUpCount > 0) {
            $actions[] = Action::make('catchUpNotes')
                ->label(__('Fill in notes (:count)', ['count' => $this->catchUpCount]))
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->modalHeading(__('Patients without notes today'))
                ->modalDescription(__('Tap a patient to add optional visit notes. Nothing is required.'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                    'filament.tenant-admin.components.catch-up-notes-list',
                    ['bookings' => $this->catchUpBookings],
                ));
        }

        if (! auth()->user()?->canOperateQueueControls()) {
            return $actions;
        }

        return array_merge($actions, [
            Action::make('patientArrived')
                ->label(__('Patient arrived'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->currentBooking?->status === 'called')
                ->action(function (): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        return;
                    }
                    app(LiveSessionService::class)->patientArrived($session);
                    Notification::make()->title(__('Patient marked as arrived'))->success()->send();
                }),
            Action::make('completeAndCallNext')
                ->label(__('Complete & call next'))
                ->icon('heroicon-o-forward')
                ->color('primary')
                ->visible(fn (): bool => $this->currentBooking?->status === 'in_chamber')
                ->form(fn (): array => auth()->user()?->canRecordVisitNotes()
                    ? VisitNotesFormSchema::components()
                    : [])
                ->modalHeading(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Complete visit')
                    : null)
                ->modalDescription(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Add optional notes, or leave everything blank and tap Complete.')
                    : null)
                ->modalSubmitActionLabel(__('Complete'))
                ->action(function (
                    array $data,
                    LiveSessionService $liveSessionService,
                    VisitRecordService $visitRecordService,
                ): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        return;
                    }

                    CompleteBookingWithVisitNotes::finishCurrentSessionPatient(
                        $data,
                        $liveSessionService,
                        $visitRecordService,
                        $session,
                    );
                }),
            Action::make('callNext')
                ->label(__('Call next patient'))
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->visible(fn (): bool => ! $this->currentBooking && $this->runningLiveSession !== null)
                ->action(function (): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        Notification::make()
                            ->title(__('No live session running'))
                            ->body(__('Start a session from Live Queue Control first.'))
                            ->warning()
                            ->send();

                        return;
                    }
                    app(LiveSessionService::class)->completeCurrentPatient($session);
                    Notification::make()->title(__('Called next patient'))->success()->send();
                }),
        ]);
    }

    public function catchUpBookingAction(): Action
    {
        return Action::make('catchUpBooking')
            ->label(__('Add notes'))
            ->form(VisitNotesFormSchema::components())
            ->modalHeading(__('Add visit notes'))
            ->modalDescription(__('All fields optional — voice, photo, diagnosis, or prescription.'))
            ->modalSubmitActionLabel(__('Save notes'))
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

                Notification::make()
                    ->title(__('Notes saved'))
                    ->success()
                    ->send();
            });
    }
}
