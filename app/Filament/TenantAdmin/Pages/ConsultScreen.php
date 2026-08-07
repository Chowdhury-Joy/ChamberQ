<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Patient;
use App\Models\VisitRecord;
use App\Filament\TenantAdmin\Concerns\AppliesVisitNotesDrafts;
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
    use AppliesVisitNotesDrafts;
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

    /**
     * Livewire caches `getXProperty()` results for the whole request, so an
     * action that moves the queue would re-render against the state it just
     * replaced — showing "Complete visit" again instead of the print/send
     * buttons until the next 3s poll. Every mutating action clears them.
     */
    private function forgetQueueState(): void
    {
        unset(
            $this->runningLiveSession,
            $this->currentBooking,
            $this->currentPatient,
            $this->visitHistory,
            $this->lastVisitRecord,
            $this->catchUpCount,
            $this->catchUpBookings,
            $this->currentVisitRecord,
        );
    }

    public function getRunningLiveSessionProperty(): ?LiveSession
    {
        return LiveSession::query()
            ->whereDate('session_date', Carbon::today())
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->with([
                'currentBooking.patient',
                'currentBooking.visitRecord.prescription.items',
                'scheduleSession.doctor',
                'scheduleSession.chamber',
            ])
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

    /**
     * Notes already written for the patient in the room right now.
     *
     * Distinct from `lastVisitRecord`, which deliberately excludes today's
     * booking so the "Last visit" panel keeps showing the previous consult.
     */
    public function getCurrentVisitRecordProperty(): ?VisitRecord
    {
        $booking = $this->currentBooking;

        if (! $booking) {
            return null;
        }

        return VisitRecord::query()
            ->where('booking_id', $booking->id)
            ->with(['condition', 'prescription.items'])
            ->first();
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
        if (! auth()->user()?->canOperateQueueControls()) {
            return [];
        }

        return [
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
                    $this->forgetQueueState();
                    Notification::make()->title(__('Patient marked as arrived'))->success()->send();
                }),
            Action::make('completeVisit')
                ->label(__('Complete visit'))
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->currentBooking?->status === 'in_chamber')
                ->form(function (Action $action): array {
                    if (! auth()->user()?->canRecordVisitNotes()) {
                        return [];
                    }

                    $arguments = $action->getArguments();
                    $forceForm = (bool) ($arguments['forceForm'] ?? false);
                    $record = $this->currentVisitRecord;

                    if ($record?->hasClinicalContent() && ! $forceForm) {
                        return VisitNotesFormSchema::summaryComponents($record);
                    }

                    return VisitNotesFormSchema::components(
                        $this->currentPatient,
                        $this->lastVisitRecord,
                    );
                })
                ->fillForm(fn (): array => auth()->user()?->canRecordVisitNotes()
                    ? VisitNotesFormSchema::stateFromRecord($this->currentVisitRecord)
                    : [])
                ->modalHeading(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Complete visit')
                    : null)
                ->modalDescription(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Check the notes, or leave everything blank and tap Complete.')
                    : null)
                ->modalSubmitActionLabel(__('Complete'))
                ->extraModalFooterActions([
                    Action::make('editVisitNotes')
                        ->label(__('Edit'))
                        ->color('gray')
                        ->visible(fn (): bool => (bool) $this->currentVisitRecord?->hasClinicalContent())
                        ->action(function (): void {
                            $this->replaceMountedAction('completeVisit', ['forceForm' => true]);
                        }),
                ])
                ->action(function (
                    array $data,
                    LiveSessionService $liveSessionService,
                    VisitRecordService $visitRecordService,
                ): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        return;
                    }

                    CompleteBookingWithVisitNotes::completeCurrentSessionPatientWithoutAdvancing(
                        $data,
                        $liveSessionService,
                        $visitRecordService,
                        $session,
                    );

                    $this->forgetQueueState();
                }),
            Action::make('callNext')
                ->label(__('Call next patient'))
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->visible(fn (): bool => $this->runningLiveSession !== null
                    && (! $this->currentBooking || $this->currentBooking->status === 'completed'))
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
                    app(LiveSessionService::class)->callNextPatient($session);
                    $this->forgetQueueState();
                    Notification::make()->title(__('Called next patient'))->success()->send();
                }),
        ];
    }

    public function catchUpNotesAction(): Action
    {
        return Action::make('catchUpNotes')
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

    /**
     * Write (or reopen and edit) the prescription while the patient is still in
     * the room. Saves without closing the visit, so the doctor can keep talking,
     * add a medicine the patient mentions late, and finish only when ready.
     */
    public function writePrescriptionAction(): Action
    {
        // "Edit" only once medicines exist — advice/diagnosis alone is notes,
        // not yet a prescription, and calling it one before any medicine is
        // added overstates what has actually been written.
        $hasPrescription = (bool) $this->currentVisitRecord?->prescription?->items->isNotEmpty();
        $label = $hasPrescription ? __('Edit prescription') : __('Write prescription');

        return Action::make('writePrescription')
            ->label($label)
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalHeading($label)
            ->modalDescription(__('Saved without ending the visit — you can reopen and change this until you tap Complete visit.'))
            ->modalSubmitActionLabel(__('Save'))
            ->form(fn (): array => VisitNotesFormSchema::components(
                $this->currentPatient,
                $this->lastVisitRecord,
            ))
            ->fillForm(fn (): array => VisitNotesFormSchema::stateFromRecord($this->currentVisitRecord))
            ->action(function (array $data, VisitRecordService $visitRecordService): void {
                $booking = $this->currentBooking;
                $user = auth()->user();

                if (! $booking || ! $user?->canRecordVisitNotes()) {
                    return;
                }

                $visitRecordService->saveForCompletedBooking($booking, $user, $data);

                $this->forgetQueueState();

                Notification::make()
                    ->title(__('Prescription saved'))
                    ->body(__('The visit is still open — tap Complete visit when you are done.'))
                    ->success()
                    ->send();
            });
    }

    public function catchUpBookingAction(): Action
    {
        return Action::make('catchUpBooking')
            ->label(__('Add notes'))
            ->form(function (Action $action): array {
                $bookingId = $action->getArguments()['bookingId'] ?? null;
                $booking = $bookingId ? Booking::with('patient')->find($bookingId) : null;

                return VisitNotesFormSchema::components($booking?->patient, null);
            })
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

                // Drops this patient out of the catch-up count straight away.
                $this->forgetQueueState();

                Notification::make()
                    ->title(__('Notes saved'))
                    ->success()
                    ->send();
            });
    }
}
