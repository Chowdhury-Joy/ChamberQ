<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Schemas;

use App\Filament\TenantAdmin\Support\PracticeRulesForm;
use App\Filament\TenantAdmin\Support\PublicMediaFields;
use App\Models\Doctor;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
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
                Toggle::make('allows_repeat_serials')
                    ->label(__('Staff may book repeating serials'))
                    ->helperText(__('For courses such as physio or dressings. Staff can put this patient on this sitting for later weeks. Off by default. Not an online membership.'))
                    ->default(false),
                Select::make('collect_fee_at_checkin')
                    ->label(__('When to collect this doctor\'s fee'))
                    ->helperText(__('Clinic default is Branding → Desk. Use this only when one doctor takes money at the door and another after checkup.'))
                    ->options([
                        'inherit' => __('Same as clinic default'),
                        '1' => __('At the door (before they see the doctor)'),
                        '0' => __('After the visit'),
                    ])
                    ->default('inherit')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return 'inherit';
                        }

                        return ((int) $state) === 1 ? '1' : '0';
                    })
                    ->dehydrateStateUsing(function (?string $state): ?int {
                        if ($state === null || $state === '' || $state === 'inherit') {
                            return null;
                        }

                        return $state === '1' ? 1 : 0;
                    })
                    ->native(false),
                Toggle::make('inherit_practice_rules')
                    ->label(__('Use clinic follow-up and room-fee rules'))
                    ->helperText(__('Clinic defaults are in Branding Settings. Turn this off to set different months or report/counseling prices for this doctor only.'))
                    ->default(true)
                    ->live()
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false),
                ...PracticeRulesForm::fieldsets(
                    '',
                    fn (Get $get): bool => (tenant()?->hasStations() ?? false)
                        && ! (bool) $get('inherit_practice_rules'),
                ),
                TextInput::make('qualifications')
                    ->label(__('Qualifications'))
                    ->placeholder(__('e.g. MBBS, FCPS (Medicine)'))
                    ->maxLength(255),
                TextInput::make('registration_number')
                    ->label(__('BM&DC registration number'))
                    ->maxLength(80),
                TextInput::make('default_fee_taka')
                    ->label(__('Consultation fee (৳)'))
                    ->helperText(__('What staff collect for a normal visit. They cannot type a different amount. Not an online payment.'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('৳'),
                Repeater::make('extra_fees')
                    ->label(__('Other visit fees'))
                    ->helperText(__('Optional. Leave empty if every visit costs the same. Add a row only if this doctor charges a different price for follow-up, dressing, or similar.'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('Name'))
                            ->placeholder(__('e.g. Follow-up'))
                            ->required()
                            ->distinct()
                            ->maxLength(80),
                        TextInput::make('amount')
                            ->label(__('Amount (৳)'))
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->suffix('৳'),
                    ])
                    ->columns(2)
                    ->default([])
                    ->addActionLabel(__('Add fee type'))
                    ->reorderable(false)
                    ->columnSpanFull(),
                TextInput::make('pharmacy_doctor_percent')
                    ->label(__('Doctor pharmacy cut (%)'))
                    ->helperText(__('Override the chamber default for this doctor. Empty = use Branding. 0 = this doctor gets no pharmacy cut. Only sales from their prescription.'))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->nullable()
                    ->visible(fn (): bool => tenant()?->hasPharmacy() ?? false),
                Fieldset::make(__('Website profile'))
                    ->visible(fn (): bool => ! tenant()?->isSoloDoctor())
                    ->columns(2)
                    ->schema([
                        Toggle::make('show_on_website')
                            ->label(__('Show on website'))
                            ->default(false)
                            ->columnSpanFull(),
                        TextInput::make('public_slug')
                            ->label(__('Profile URL slug'))
                            ->helperText(__('Used in /doctors/your-slug'))
                            ->maxLength(120),
                        TextInput::make('public_title')
                            ->label(__('Title on website'))
                            ->placeholder(__('e.g. Consultant Physiotherapist'))
                            ->maxLength(255),
                        PublicMediaFields::image(
                            'photo_url',
                            'doctor-photos',
                            __('Photo'),
                            __('Upload a portrait from this computer (JPG, PNG, or WebP, up to 4 MB). An older pasted link still works until you replace it.'),
                        )->columnSpanFull(),
                        TextInput::make('website_sort_order')
                            ->label(__('Sort order'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        \Filament\Forms\Components\RichEditor::make('bio')
                            ->label(__('Bio'))
                            ->columnSpanFull(),
                    ]),
                Fieldset::make(__('Patient notifications'))
                    ->description(__('Each doctor can differ. Tick Auto SMS (ChamberQ texts them), Push SMS (staff tap Send SMS), and/or Push WhatsApp (staff tap WhatsApp). WhatsApp is never sent by itself.'))
                    ->schema([
                        self::notifyStage(
                            Doctor::NOTIFY_BOOKING_CONFIRMATION,
                            __('Booking confirmation'),
                            __('After a patient books a serial. Auto SMS uses 1 prepaid credit. Push buttons show on Book serial and the day list.'),
                        ),
                        self::notifyStage(
                            Doctor::NOTIFY_DOCTOR_LATE,
                            __('Doctor late'),
                            __('When staff mark delay. Auto SMS texts everyone waiting (1 credit each). Push SMS / WhatsApp appear on Tell waiting patients.'),
                        ),
                        self::notifyStage(
                            Doctor::NOTIFY_CANCELLATION,
                            __('Cancellation'),
                            __('Auto SMS texts remaining patients when staff finish early or mark the doctor absent. Closed days always need a tap — tick Push SMS or Push WhatsApp for those.'),
                        ),
                        self::notifyStage(
                            Doctor::NOTIFY_PRESCRIPTION,
                            __('After visit'),
                            __('Prescription link and Google review. Auto SMS sends when the visit is completed. Push buttons stay on the desk for a tap.'),
                        ),
                        self::notifyStage(
                            Doctor::NOTIFY_FOLLOW_UP,
                            __('Follow-up reminder'),
                            __('3 days before the follow-up date. Auto SMS runs in the morning. Push SMS / WhatsApp appear on Follow-up reminders for staff to tap.'),
                        ),
                    ]),
            ]);
    }

    private static function notifyStage(string $stage, string $label, string $helper): CheckboxList
    {
        return CheckboxList::make('notify_channels.'.$stage)
            ->label($label)
            ->helperText($helper)
            ->options(Doctor::notifyDeliveryOptions())
            ->default(Doctor::selectedNotifyDeliveries(Doctor::defaultNotifyChannels()[$stage]))
            ->formatStateUsing(fn (mixed $state): array => Doctor::selectedNotifyDeliveries($state))
            ->dehydrateStateUsing(fn (mixed $state): array => Doctor::notifyDeliveriesFromSelection($state))
            ->columns(1)
            ->columnSpanFull();
    }
}
