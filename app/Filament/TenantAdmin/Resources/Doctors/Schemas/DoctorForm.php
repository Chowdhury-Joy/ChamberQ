<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Schemas;

use App\Models\Doctor;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->extraInputAttributes(['name' => 'name'])
                    ->autocomplete('name')
                    ->required(),
                Select::make('user_id')
                    ->label(__('Login account'))
                    ->helperText(__('Pair this doctor with the account they sign in with, so their own medicine list follows their practice type outside a consult. Leave blank for a visiting doctor who never logs in.'))
                    ->options(fn (): array => User::query()
                        ->where('role', User::ROLE_DOCTOR)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    // One account, one profile: the DB unique index says so, and
                    // a clash here would silently hand a prescription the wrong name.
                    ->unique(
                        table: 'doctors',
                        column: 'user_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', tenant('id')),
                    )
                    ->validationMessages([
                        'unique' => __('That account is already paired with another doctor.'),
                    ]),
                Select::make('practice_type')
                    ->label(__('Practice type'))
                    ->options(Doctor::practiceTypeOptions())
                    ->default(Doctor::PRACTICE_GENERAL)
                    ->required()
                    ->native(false),
                Toggle::make('staff_may_enter_prescriptions')
                    ->label(__('Staff may type this doctor\'s prescriptions'))
                    ->helperText(__('For doctors who write on paper as usual and let staff key it in afterwards. Staff get the medicine list and follow-up only — never the diagnosis, voice notes or past visits. Off by default.'))
                    ->default(false),
                TextInput::make('qualifications')
                    ->label(__('Qualifications'))
                    ->placeholder(__('e.g. MBBS, FCPS (Medicine)'))
                    ->maxLength(255),
                TextInput::make('registration_number')
                    ->label(__('BM&DC registration number'))
                    ->maxLength(80),
                Fieldset::make(__('Patient notifications'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_BOOKING_CONFIRMATION.'.sms')
                            ->label(__('Booking confirmation — SMS'))
                            ->helperText(__('Automatic text after a patient books. Uses 1 prepaid credit.'))
                            ->default(true),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_BOOKING_CONFIRMATION.'.whatsapp')
                            ->label(__('Booking confirmation — WhatsApp'))
                            ->helperText(__('Reserved for staff re-send; patients already share their ticket. Off by default.'))
                            ->default(false),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_DOCTOR_LATE.'.sms')
                            ->label(__('Doctor late — SMS'))
                            ->helperText(__('Automatic text to waiting patients when staff mark delay. 1 credit each.'))
                            ->default(false),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_DOCTOR_LATE.'.whatsapp')
                            ->label(__('Doctor late — WhatsApp'))
                            ->helperText(__('Shows tap-to-send WhatsApp links after Mark Late. Free; staff must tap.'))
                            ->default(false),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_CANCELLATION.'.sms')
                            ->label(__('Cancellation — SMS'))
                            ->helperText(__('Staff tap Send SMS per patient (vacation / end session). 1 credit each.'))
                            ->default(false),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_CANCELLATION.'.whatsapp')
                            ->label(__('Cancellation — WhatsApp'))
                            ->helperText(__('Shows tap-to-send WhatsApp links. Free; staff must tap.'))
                            ->default(true),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_PRESCRIPTION.'.sms')
                            ->label(__('Prescription — SMS'))
                            ->helperText(__('Staff tap Send SMS with the 48h prescription link. 1 credit.'))
                            ->default(false),
                        Toggle::make('notify_channels.'.Doctor::NOTIFY_PRESCRIPTION.'.whatsapp')
                            ->label(__('Prescription — WhatsApp'))
                            ->helperText(__('Shows Send via WhatsApp after the visit. Free; staff must tap.'))
                            ->default(true),
                    ]),
            ]);
    }
}
