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
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Patient;
use Carbon\Carbon;
use App\Services\BookingService;
use App\Services\LiveSessionService;
use App\Services\MedicineService;
use App\Services\PatientService;
use App\Services\VisitRecordService;
use Filament\Notifications\Notification;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class DailyRoster extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.tenant-admin.pages.daily-roster';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageQueue() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->whereDate('booking_date', today())
                    ->with(['visitRecord.prescription'])
                    ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')->label('Serial'),
                TextColumn::make('patient_name')->label('Name')->searchable(),
                TextColumn::make('patient_phone')->label('Phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'primary',
                    }),
            ])
            ->recordActions([
                Action::make('call')
                    ->label('Call to Chamber')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => auth()->user()?->canOperateQueueControls()
                        && $record->status === 'waiting')
                    ->action(fn (Booking $record) => app(LiveSessionService::class)->bringBookingToChamber($record)),

                VisitNotesFormSchema::configureModal(Action::make('complete'))
                    ->label('Mark Completed')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => auth()->user()?->canOperateQueueControls()
                        && in_array($record->status, ['waiting', 'in_chamber', 'called']))
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
                    ->modalSubmitActionLabel(__('Complete'))
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
                        ? __('Edit prescription')
                        : __('Enter prescription'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => static::staffMayEnterPrescriptionFor($record))
                    ->modalHeading(__('Enter paper prescription'))
                    ->modalDescription(__('Copy in what the doctor wrote by hand. This does not change the visit status.'))
                    ->modalSubmitActionLabel(__('Save prescription'))
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
            ])
            ->headerActions([
                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary')
                    ->visible(fn (): bool => auth()->user()?->canAccessLiveQueueControl() ?? false),

                Action::make('newWalkIn')
                    ->label(__('New Walk-In'))
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
                            true,
                            $patientId,
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
     * Today's bookable sessions and lab windows, labelled so staff can tell two
     * identically-named sessions apart.
     *
     * @return array<string, string>
     */
    protected static function todaysBookableOptions(): array
    {
        $today = today()->dayOfWeek;
        $options = [];

        $sessions = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $today)
            ->get();

        foreach ($sessions as $session) {
            $options['session:' . $session->id] = sprintf(
                '%s — %s (%s, %s–%s)',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
            );
        }

        if (tenant()?->hasFeature('lab_tests')) {
            $slots = LabCollectionSlot::with('chamber')->where('day_of_week', $today)->get();

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
}
