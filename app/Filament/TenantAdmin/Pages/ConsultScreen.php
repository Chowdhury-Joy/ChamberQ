<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Support\StaffDeskJobs;
use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\LiveSession;
use App\Models\MedicineUsage;
use App\Models\ScheduleSession;
use App\Models\Patient;
use App\Models\VisitRecord;
use App\Filament\TenantAdmin\Concerns\AppliesVisitNotesDrafts;
use App\Filament\TenantAdmin\Support\CompleteBookingWithVisitNotes;
use App\Filament\TenantAdmin\Support\StationsHandoffForm;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Services\CrossTenantClinicalHistoryService;
use App\Services\LiveSessionService;
use App\Services\MedicineService;
use App\Services\PrescriptionTemplateService;
use App\Services\SittingPrompt;
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
use Livewire\Attributes\Renderless;

class ConsultScreen extends Page implements HasActions
{
    use AppliesVisitNotesDrafts;
    use InteractsWithActions;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    /**
     * Print onto pads that already have the doctor's name. The desk tick
     * remembers this in the browser; Preview and Save & print share it so
     * the iframe cannot disagree with the printer.
     */
    public bool $printOnMyPaper = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Consult Screen';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.tenant-admin.pages.consult-screen';

    protected Width | string | null $maxContentWidth = Width::Full;

    /**
     * How many of the doctor's own medicines appear as chips on the pad.
     * Enough to cover a chamber's common set without the strip wrapping into
     * a wall that pushes the prescription table below the fold.
     */
    private const MY_MEDICINE_CHIPS = 8;

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

    public function getSittingPromptsProperty(): \Illuminate\Support\Collection
    {
        return app(SittingPrompt::class)->promptsForToday();
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
                'currentBooking.bookable',
                'currentBooking.procedureBookings.bookable',
                'currentBooking.relatedBooking.bookable',
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
        $handoff = [];
        if (tenant()?->hasStations()) {
            $handoff = [
                StationsHandoffForm::bookAction(
                    Action::make('bookIntervention'),
                    fn (): ?Booking => $this->currentBooking,
                ),
                StationsHandoffForm::moveAction(
                    Action::make('moveIntervention'),
                    fn (): ?Booking => $this->currentBooking,
                ),
                StationsHandoffForm::sendToMskAction(
                    Action::make('sendToMsk'),
                    fn (): ?Booking => $this->currentBooking,
                ),
                StationsHandoffForm::sendToReportAction(
                    Action::make('sendToReport'),
                    fn (): ?Booking => $this->currentBooking,
                ),
                StationsHandoffForm::sendToCounselingAction(
                    Action::make('sendToCounseling'),
                    fn (): ?Booking => $this->currentBooking,
                ),
            ];
        }

        if (! auth()->user() instanceof \App\Models\User || ! StaffDeskJobs::canRunQueue(auth()->user())) {
            return $handoff;
        }

        return [
            ...$handoff,
            Action::make('patientArrived')
                ->label('Patient arrived')
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
                ->label('Complete visit')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'cs-complete-visit-btn'])
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
                ->modalSubmitActionLabel('Complete')
                ->extraModalFooterActions([
                    Action::make('editVisitNotes')
                        ->label('Edit')
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
                ->label('Call next patient')
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->visible(fn (): bool => $this->runningLiveSession !== null
                    && $this->runningLiveSession->status !== 'paused'
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
            Action::make('doctorSteppedOut')
                ->label('Doctor stepped out')
                ->icon('heroicon-o-pause')
                ->color('gray')
                ->visible(fn (): bool => $this->runningLiveSession?->status === 'active')
                ->form([
                    \Filament\Forms\Components\TextInput::make('reason')
                        ->label(__('Reason (e.g. Prayer break)'))
                        ->required(),
                    \Filament\Forms\Components\Select::make('estimated_minutes')
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
                ->action(function (array $data): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        return;
                    }
                    app(LiveSessionService::class)->pauseSession(
                        $session,
                        $data['reason'],
                        (int) $data['estimated_minutes'],
                    );
                    $this->forgetQueueState();
                    Notification::make()->title(__('Doctor stepped out'))->warning()->send();
                }),
            Action::make('doctorBack')
                ->label("He's back")
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (): bool => $this->runningLiveSession?->status === 'paused')
                ->action(function (): void {
                    $session = $this->runningLiveSession;
                    if (! $session) {
                        return;
                    }
                    app(LiveSessionService::class)->resumeSession($session);
                    $this->forgetQueueState();
                    Notification::make()->title(__('Session resumed'))->success()->send();
                }),
        ];
    }

    /**
     * Desktop Rx pad Save — one Alpine payload, no per-chip Livewire round trips.
     *
     * Returns the doctor print URL when a prescription exists, so Preview /
     * Save & print can open it without a second round trip.
     *
     * Renderless: the pad is `wire:ignore` and Alpine already holds everything
     * on screen, so a re-render has no HTML to contribute and only risks
     * morphing the subtree the doctor is typing into. Notifications and the
     * returned print URL travel on the response either way — `Renderless` drops
     * the HTML diff, not the dispatches or the return value.
     *
     * @param  array<string, mixed>  $data
     */
    #[Renderless]
    public function saveRxDesk(array $data, VisitRecordService $visitRecordService): ?string
    {
        return $this->writeRxDesk($data, $visitRecordService, announce: true);
    }

    /**
     * The same write, fired by the desk itself a second after the doctor stops
     * typing and again the moment they click anything outside the pad.
     *
     * The desk held the whole prescription in Alpine memory and only reached
     * the server from **Preview** or **Save & print**. **Complete visit** is a
     * page header action that fills its form from the *stored* record, so a
     * doctor who wrote the script and then tapped the green button ended the
     * visit with whatever had last been saved — usually nothing — and was told
     * the visit completed. Nothing on screen said the pad was unsaved.
     *
     * Silent by design: a toast and a safety re-check every couple of seconds
     * would train the doctor to dismiss both. Explicit Save keeps them, which
     * is why this is a separate entry point rather than a flag on the payload —
     * the client cannot ask for the quiet version of an explicit save.
     *
     * @param  array<string, mixed>  $data
     */
    #[Renderless]
    public function autosaveRxDesk(array $data, VisitRecordService $visitRecordService): ?string
    {
        return $this->writeRxDesk($data, $visitRecordService, announce: false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeRxDesk(array $data, VisitRecordService $visitRecordService, bool $announce): ?string
    {
        $booking = $this->currentBooking;
        $user = auth()->user();

        if (! $booking || $booking->status !== 'in_chamber' || ! $user?->canRecordVisitNotes()) {
            return null;
        }

        if (! $user->attachesVisitPaperOnConsult()) {
            unset($data['report_photos'], $data['prescription_photo']);
        }

        $record = $visitRecordService->saveForCompletedBooking($booking, $user, $data);

        $this->forgetQueueState();

        if ($announce) {
            Notification::make()
                ->title(__('Prescription saved'))
                ->success()
                ->send();

            $this->warnAboutRxSafety($data);
        }

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
            ->modalHeading('Prescription preview')
            ->modalDescription(__('This is exactly what will print.'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                'filament.tenant-admin.components.rx-preview',
                ['url' => $this->prescriptionPrintUrl()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * Built here rather than taken from the browser: the preview URL decides
     * what gets framed, so it is resolved from the record on the server.
     */
    private function prescriptionPrintUrl(?VisitRecord $record = null): ?string
    {
        $prescription = ($record ?? $this->currentVisitRecord)?->prescription;

        if (! $prescription) {
            return null;
        }

        $url = tenant_web_route('prescriptions.print', ['prescription' => $prescription]);

        return $this->printOnMyPaper
            ? $url.(str_contains($url, '?') ? '&' : '?').'paper=1'
            : $url;
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
     * The doctor's own shortlist, as one-tap chips on the pad.
     *
     * A chamber doctor prescribes the same small set all day. Until now those
     * entries only surfaced if he typed into the search box — the list he
     * curates by hand was one step further away than a drug he had never used.
     *
     * **Alphabetical, and capped.** Not ordered by how often he prescribes
     * something: a row that rearranges itself from behaviour he cannot see is
     * exactly what was ruled out (`decisions.md` 2026-08-11), and a strip whose
     * buttons move between patients is worse than no strip. The full list is
     * still one keystroke away in the search box.
     *
     * @return list<array<string, mixed>>
     */
    public function getMyMedicinesProperty(): array
    {
        $user = auth()->user();

        if (! $user?->canRecordVisitNotes()) {
            return [];
        }

        return MedicineUsage::query()
            ->where('user_id', $user->id)
            ->whereNull('hidden_at')
            ->orderBy('medicine_name')
            ->limit(self::MY_MEDICINE_CHIPS)
            ->get()
            ->map(fn (MedicineUsage $usage): array => [
                'brand_name' => $usage->medicine_name,
                'generic_name' => $usage->generic_name,
                'dose' => $usage->last_dose,
                'frequency' => $usage->last_frequency,
                'duration' => $usage->last_duration,
                'timing' => $usage->last_timing,
            ])
            ->values()
            ->all();
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
        $label = $hasPrescription ? 'Edit prescription' : __('Write prescription');

        return VisitNotesFormSchema::configureModal(Action::make('writePrescription'))
            ->label($label)
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalHeading($label)
            ->modalSubmitActionLabel('Save')
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

    public function startSessionFromPrompt(int $scheduleSessionId): void
    {
        if (! auth()->user() instanceof \App\Models\User || ! StaffDeskJobs::canRunQueue(auth()->user())) {
            return;
        }

        $this->promptSessionId = $scheduleSessionId;
        $this->mountStartSessionOrRun();
    }

    public function mountMarkLateForPrompt(int $scheduleSessionId, int $suggestedMinutes): void
    {
        if (! auth()->user() instanceof \App\Models\User || ! StaffDeskJobs::canRunQueue(auth()->user())) {
            return;
        }

        $this->promptSessionId = $scheduleSessionId;
        $this->mountAction('markLate', ['delay_minutes' => $suggestedMinutes]);
    }

    public ?int $promptSessionId = null;

    public function doStartSession(): void
    {
        if (! $this->promptSessionId) {
            return;
        }

        $scheduleSession = ScheduleSession::findOrFail($this->promptSessionId);
        app(LiveSessionService::class)->startSession($scheduleSession);

        Notification::make()->title(__('Session Started'))->success()->send();
        $this->forgetQueueState();
        $this->promptSessionId = null;
    }

    public function startSessionAction(): Action
    {
        return Action::make('startSession')
            ->label('Start live session')
            ->color('success')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cancel')
            ->modalHeading(fn (): string => match ($this->consultStartModalKind()) {
                'early_during_delay' => __('Start before the announced time?'),
                default => __('Start after sitting time?'),
            })
            ->modalDescription(fn (): ?string => $this->consultStartModalDescription())
            ->extraModalFooterActions(fn (): array => $this->consultStartModalFooterActions())
            ->action(fn () => $this->doStartSession());
    }

    public function markLateAction(): Action
    {
        $live = $this->promptLiveSession();
        $currentDelay = (int) ($live?->delay_minutes ?? 0);

        return Action::make('markLate')
            ->label($live?->status === 'delayed' ? 'Add time' : 'Mark Late')
            ->color('warning')
            ->fillForm(fn (array $arguments): array => [
                'delay_minutes' => $arguments['delay_minutes']
                    ?? array_key_first(app(SittingPrompt::class)->delayOptionsFor($currentDelay)),
            ])
            ->form([
                \Filament\Forms\Components\Select::make('delay_minutes')
                    ->label($live?->status === 'delayed' ? __('Additional delay (total)') : __('Delay Duration'))
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
            ->action(function (array $data): void {
                if (! $this->promptSessionId) {
                    return;
                }

                $scheduleSession = ScheduleSession::findOrFail($this->promptSessionId);

                $liveSession = LiveSession::firstOrCreate([
                    'tenant_id' => tenant('id'),
                    'schedule_session_id' => $scheduleSession->id,
                    'session_date' => Carbon::today()->toDateString(),
                ], [
                    'status' => 'delayed',
                ]);

                app(LiveSessionService::class)->markDelay($liveSession, (int) $data['delay_minutes']);

                Notification::make()->title(__('Session Delayed'))->success()->send();
                $this->promptSessionId = null;
                $this->forgetQueueState();
            });
    }

    public function mountStartSessionOrRun(): void
    {
        if ($this->consultStartModalKind() === null) {
            $this->doStartSession();

            return;
        }

        $this->mountAction('startSession');
    }

    protected function promptLiveSession(): ?LiveSession
    {
        if (! $this->promptSessionId) {
            return null;
        }

        return LiveSession::query()
            ->where('schedule_session_id', $this->promptSessionId)
            ->where('session_date', Carbon::today()->toDateString())
            ->first();
    }

    protected function consultStartModalKind(): ?string
    {
        if (! $this->promptSessionId) {
            return null;
        }

        $scheduleSession = ScheduleSession::find($this->promptSessionId);

        if (! $scheduleSession) {
            return null;
        }

        return app(SittingPrompt::class)->startModalKind(
            $scheduleSession,
            $this->promptLiveSession(),
        );
    }

    protected function consultStartModalDescription(): ?string
    {
        $prompt = app(SittingPrompt::class)->promptForSession(
            ScheduleSession::find($this->promptSessionId),
            $this->promptLiveSession(),
        );

        return match ($this->consultStartModalKind()) {
            'early_during_delay' => $prompt['message'] ?? null,
            'late_without_notice' => __('Patients have not been told the doctor is late. Mark Late to slide the ticket, or just start and the clock will follow when you actually begin.'),
            default => null,
        };
    }

    /**
     * @return list<Action>
     */
    protected function consultStartModalFooterActions(): array
    {
        $kind = $this->consultStartModalKind();

        if ($kind === null) {
            return [];
        }

        $prompt = app(SittingPrompt::class)->promptForSession(
            ScheduleSession::find($this->promptSessionId),
            $this->promptLiveSession(),
        );

        if ($kind === 'early_during_delay') {
            $announcedAt = $prompt['announced_at'] ?? null;

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

        $suggested = $prompt['suggested_delay_minutes']
            ?? app(SittingPrompt::class)->suggestedDelayMinutes(
                (int) ($prompt['minutes_late'] ?? 15),
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
}
