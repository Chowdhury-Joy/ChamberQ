<?php

namespace App\Filament\TenantAdmin\Support;

use App\Filament\TenantAdmin\Resources\Patients\Schemas\PatientForm;
use App\Models\LabCollectionSlot;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use App\Services\PatientService;
use App\Support\StaffDeskScope;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Phone / call-centre booking: pick a date, then a public sitting, then
 * the patient. Same write path as the website; published cap only.
 */
final class StaffBookingForm
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        return [
            DatePicker::make('booking_date')
                ->label(__('Date'))
                ->helperText(__('Any open sitting day from today through the booking window. Same window as the public website.'))
                ->native(false)
                ->required()
                ->minDate(now()->startOfDay())
                ->maxDate(PlatformSetting::onlineBookingMaxDate())
                ->displayFormat('D, j M Y')
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('bookable', null)),
            Select::make('bookable')
                ->label(__('Sitting'))
                ->helperText(__('Visit sittings only — same rooms a patient can pick on the website. Walk-ins onto today’s MSK or OT stay on Daily Roster.'))
                ->options(fn (Get $get): array => self::bookableOptions((string) ($get('booking_date') ?? '')))
                ->required()
                ->native(false)
                ->searchable()
                ->preload(),
            TextInput::make('patient_phone')
                ->label(__('Phone number'))
                ->extraInputAttributes([
                    'name' => 'patient_phone',
                    'form' => 'cq-no-native-form',
                ])
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
                ->extraInputAttributes([
                    'name' => 'patient_name',
                    'form' => 'cq-no-native-form',
                ])
                ->autocomplete('name')
                ->required(),
            PatientForm::yearOfBirthInput(),
            TextInput::make('nid')
                ->label(__('NID number (optional)'))
                ->helperText(__('From the national ID card — helps reconnect records if the phone number changes.'))
                ->maxLength(17)
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! \App\Support\BdNid::isValid((string) $value)) {
                        $fail(__('Please enter a valid NID (10 or 13 digits), or leave it blank.'));
                    }
                }),
            Checkbox::make('share_clinical_history')
                ->label(__('Share health records with other ChamberQ doctors'))
                ->helperText(__('Visit notes and prescriptions can help the next ChamberQ doctor treat this patient safely. Voice notes and photos stay in this clinic.'))
                ->default(true),
            Checkbox::make('seen_before_software')
                ->label(__('They have been treated here before ChamberQ'))
                ->helperText(__('Tick if they are an old paper-file patient. The doctor will see them as returning, not a first visit. You can also tap this on their row later.'))
                ->default(false),
            Select::make('referring_doctor_id')
                ->label(__('Referred by (outside GP)'))
                ->options(fn (): array => ReferringDoctor::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                    ->all())
                ->placeholder(__('Walk-in / no referrer'))
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => tenant()?->hasReferrals() ?? false),
        ];
    }

    /**
     * Public sittings (and lab windows) that meet on this calendar date.
     *
     * @return array<string, string>
     */
    public static function bookableOptions(string $ymd): array
    {
        if ($ymd === '') {
            return [];
        }

        try {
            $date = Carbon::parse($ymd);
        } catch (\Throwable) {
            return [];
        }

        $dow = $date->dayOfWeek;
        $options = [];
        $user = auth()->user();
        $bookingService = app(BookingService::class);

        $sessionsQuery = ScheduleSession::query()
            ->with(['doctor', 'chamber'])
            ->publiclyBookable()
            ->where('day_of_week', $dow);

        if ($user instanceof \App\Models\User) {
            StaffDeskScope::constrainScheduleSessions($sessionsQuery, $user);
        }

        foreach ($sessionsQuery->get() as $session) {
            $availability = $bookingService->availabilityFor($session, $date->toDateString());
            $kindSuffix = (tenant()?->hasStations() && filled($session->kind))
                ? ' · '.($session->kindLabel() ?? $session->kind)
                : '';
            $seats = $availability['blocked']
                ? __('Closed')
                : __(':remaining of :cap left', [
                    'remaining' => $availability['remaining'],
                    'cap' => $availability['cap'],
                ]);

            $options['session:'.$session->id] = sprintf(
                '%s — %s (%s, %s–%s)%s · %s',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
                $kindSuffix,
                $seats,
            );
        }

        if (tenant()?->hasFeature('lab_tests')) {
            $labQuery = LabCollectionSlot::query()->with('chamber')->where('day_of_week', $dow);
            if ($user instanceof \App\Models\User) {
                $chamberIds = StaffDeskScope::chamberIdsFor($user);
                if ($chamberIds !== null) {
                    $labQuery->whereIn('chamber_id', $chamberIds);
                }
            }

            foreach ($labQuery->get() as $slot) {
                $availability = $bookingService->availabilityFor($slot, $date->toDateString());
                $seats = $availability['blocked']
                    ? __('Closed')
                    : __(':remaining of :cap left', [
                        'remaining' => $availability['remaining'],
                        'cap' => $availability['cap'],
                    ]);

                $options['lab:'.$slot->id] = sprintf(
                    '%s — %s (%s–%s) · %s',
                    __('Lab collection'),
                    $slot->chamber?->name ?? __('Main lab'),
                    Carbon::parse($slot->start_time)->format('g:i A'),
                    Carbon::parse($slot->end_time)->format('g:i A'),
                    $seats,
                );
            }
        }

        return $options;
    }
}
