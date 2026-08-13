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
use App\Services\CrossTenantClinicalHistoryService;
use App\Services\LiveSessionService;
use App\Services\MedicineService;
use App\Services\PrescriptionTemplateService;
use App\Services\VisitRecordService;
use App\Support\RxSafety;
use App\Support\SharedClinicalVisit;
use App\Support\VitalsTrend;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

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

    protected Width | string | null $maxContentWidth = Width::Full;

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
            $this->sharedVisitHistory,
            $this->sharedClinicalWarnings,
            $this->lastVisitRecord,
            $this->currentVisitRecord,
        );
    }

    public function getRunningLiveSessionProperty(): ?LiveSession
    {
        return LiveSession::query()
            ->where('session_date', Carbon::today()->toDateString())
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
     * Completed visits from other ChamberQ chambers for the same person
     * (phone + name) when share is on. Cached inside the service so the 3s
     * poll does not re-query every tick.
     *
     * @return \Illuminate\Support\Collection<int, SharedClinicalVisit>
     */
    public function getSharedVisitHistoryProperty(): \Illuminate\Support\Collection
    {
        $patient = $this->currentPatient;

        if (! $patient) {
            return collect();
        }

        return app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor(
            $patient,
            auth()->id(),
        );
    }

    /**
     * Allergy / condition / medicine warnings recorded on matching shared
     * patient rows at other chambers (local fields still win on the header).
     *
     * @return array{allergies: list<string>, conditions: list<string>, medicines: list<string>}
     */
    public function getSharedClinicalWarningsProperty(): array
    {
        $patient = $this->currentPatient;

        if (! $patient) {
            return ['allergies' => [], 'conditions' => [], 'medicines' => []];
        }

        $matches = app(CrossTenantClinicalHistoryService::class)->matchingSharedPatients(
            $patient,
            auth()->id(),
        );

        $pick = function (string $field) use ($matches, $patient): array {
            $local = trim((string) ($patient->{$field} ?? ''));

            return $matches
                ->map(fn (Patient $other) => trim((string) ($other->{$field} ?? '')))
                ->filter(fn (string $value) => $value !== '' && strcasecmp($value, $local) !== 0)
                ->unique(fn (string $value) => mb_strtolower($value))
                ->values()
                ->all();
        };

        return [
            'allergies' => $pick('allergies'),
            'conditions' => $pick('conditions'),
            'medicines' => $pick('medicines'),
        ];
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

    /**
     * Weight / BP points from past visits for the trend charts.
     *
     * @return array{weight: list<array{label: string, value: float}>, systolic: list<array{label: string, value: int}>, diastolic: list<array{label: string, value: int}>}
     */
    public function getVitalsTrendProperty(): array
    {
        $patient = $this->currentPatient;

        if (! $patient) {
            return ['weight' => [], 'systolic' => [], 'diastolic' => []];
        }

        $records = $patient->bookings()
            ->where('status', 'completed')
            ->with('visitRecord')
            ->orderBy('booking_date')
            ->orderBy('completed_at')
            ->get()
            ->map(fn (Booking $booking): ?VisitRecord => $booking->visitRecord)
            ->filter()
            ->values();

        $external = app(CrossTenantClinicalHistoryService::class)
            ->sharedVisitRecordsFor($patient, auth()->id());

        $merged = $records
            ->concat($external)
            ->sortBy(fn (VisitRecord $record) => [
                $record->booking?->booking_date?->toDateString() ?? $record->recorded_at?->toDateString() ?? '',
                $record->recorded_at?->timestamp ?? 0,
            ])
            ->values();

        return VitalsTrend::fromVisitRecords($merged);
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
            VisitNotesFormSchema::configureModal(Action::make('completeVisit'))
                ->label(__('Complete visit'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
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
                        $this->currentBooking,
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

    /**
     * Desktop Rx pad Save — one Alpine payload, no per-chip Livewire round trips.
     *
     * Returns the doctor print URL when a prescription exists, so Preview /
     * Save & print can open it without a second round trip.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveRxDesk(array $data, VisitRecordService $visitRecordService): ?string
    {
        $booking = $this->currentBooking;
        $user = auth()->user();

        if (! $booking || $booking->status !== 'in_chamber' || ! $user?->canRecordVisitNotes()) {
            return null;
        }

        $record = $visitRecordService->saveForCompletedBooking($booking, $user, $data);

        $this->forgetQueueState();

        Notification::make()
            ->title(__('Prescription saved'))
            ->success()
            ->send();

        $this->warnAboutRxSafety($data);

        return $this->prescriptionPrintUrl($record);
    }

    /**
     * Preview the script without leaving the desk.
     *
     * An iframe of the real print route rather than a re-rendered summary:
     * a preview that is built separately is a preview that can disagree with
     * what comes out of the printer. Opening it in a modal keeps the patient's
     * chart, the queue and the unsaved pad state one Escape away — a new tab
     * put the doctor on a bare page with the browser's Back button as the way
     * home, mid-consult.
     */
    public function previewPrescriptionAction(): Action
    {
        return Action::make('previewPrescription')
            ->modalHeading(__('Prescription preview'))
            ->modalDescription(__('This is exactly what will print.'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                'filament.tenant-admin.components.rx-preview',
                ['url' => $this->prescriptionPrintUrl()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    /**
     * Built here rather than taken from the browser: the preview URL decides
     * what gets framed, so it is resolved from the record on the server.
     */
    private function prescriptionPrintUrl(?VisitRecord $record = null): ?string
    {
        $prescription = ($record ?? $this->currentVisitRecord)?->prescription;

        return $prescription
            ? tenant_web_route('prescriptions.print', ['prescription' => $prescription])
            : null;
    }




    /**
     * Re-run the safety checks on the server, at save.
     *
     * The desktop pad already shows these live — but it computes them in its
     * own Alpine copy of the rules (`rx-desk.blade.php`, `safetyWarnings()`),
     * which no test exercises, while the tested implementation
     * (`App\Support\RxSafety`, `RxSafetyTest`) only ever ran inside the phone
     * modal. Two copies of one clinical rule, nothing asserting they agree, and
     * the surface most doctors use running the untested one.
     *
     * They already disagree in small ways — the PHP version stops at the first
     * allergy token that matches a medicine, the JavaScript one reports every
     * matching token — which is cosmetic today and exactly how a real gap would
     * begin. So rather than police the duplication by convention, the server
     * checks again at the one point every desk save passes through
     * (`CLAUDE.md`: put the guard where the code converges).
     *
     * Still advisory. It warns after the save, never blocks it — a doctor may
     * have a good reason, and notes have never been allowed to hold up the
     * queue.
     *
     * @param  array<string, mixed>  $data
     */
    private function warnAboutRxSafety(array $data): void
    {
        $warnings = RxSafety::allWarnings(
            $this->currentPatient?->allergies,
            array_values(array_filter(
                (array) ($data['prescription_items'] ?? []),
                'is_array',
            )),
        );

        if ($warnings === []) {
            return;
        }

        Notification::make()
            ->title(trans_choice(
                'Check :count safety note|Check :count safety notes',
                count($warnings),
                ['count' => count($warnings)],
            ))
            ->body(implode("\n", $warnings)."\n\n".__(RxSafety::DISCLAIMER))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRxPacksProperty(): array
    {
        $user = auth()->user();

        return $user ? app(PrescriptionTemplateService::class)->forDoctor($user) : [];
    }

    /**
     * "Save as my default" — the star on an Rx desk row.
     *
     * Deliberately an explicit tap rather than something the app learns by
     * watching what gets prescribed: a doctor's shortlist stays a list he can
     * see and edit on **My medicines** (owner decision, 2026-08-11). This is
     * the same write path that page uses, reached from where the doctor
     * already is.
     *
     * @param  array<string, mixed>  $item
     */
    public function saveMedicineDefault(array $item, MedicineService $medicineService): void
    {
        $user = auth()->user();

        if (! $user?->canRecordVisitNotes() || blank($item['medicine_name'] ?? null)) {
            return;
        }

        $usage = $medicineService->saveDoctorMedicine($user, [
            'medicine_name' => (string) $item['medicine_name'],
            'generic_name' => $item['generic_name'] ?? null,
            'dose' => $item['dose'] ?? null,
            'frequency' => $item['frequency'] ?? null,
            'duration' => $item['duration'] ?? null,
            'timing' => $item['timing'] ?? null,
        ]);

        Notification::make()
            ->title(__(':medicine saved to My medicines', ['medicine' => $usage->medicine_name]))
            ->body(__('This line will fill itself next time. Edit it any time on My medicines.'))
            ->success()
            ->send();
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

        return VisitNotesFormSchema::configureModal(Action::make('writePrescription'))
            ->label($label)
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalHeading($label)
            ->modalSubmitActionLabel(__('Save'))
            ->form(fn (): array => VisitNotesFormSchema::components(
                $this->currentPatient,
                $this->lastVisitRecord,
                $this->currentBooking,
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
                    ->success()
                    ->send();
            });
    }
}
