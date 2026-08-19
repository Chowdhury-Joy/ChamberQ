<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use App\Models\DiscountCode;
use App\Models\MedicalRepresentative;
use App\Models\Tenant;
use App\Services\CommissionService;
use App\Services\DealCommissionRates;
use App\Services\PlanPricingService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make(__('Identity'))
                    ->schema([
                        TextInput::make('id')
                            ->label('URL slug (platform path)')
                            ->helperText(__('Patients reach this tenant at /{slug} on your main domain, e.g. /drkarim/book. Lowercase letters, numbers, and dashes only.'))
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9\-]+$/')
                            // The slug is the tenants table primary key, so a
                            // collision is a 500 rather than a field error.
                            ->unique(ignoreRecord: true)
                            // A slug matching a reserved path prefix routes to
                            // the platform (e.g. /admin), leaving that tenant's
                            // site permanently unreachable.
                            ->notIn(fn (): array => config('tenancy.reserved_path_prefixes', []))
                            ->validationMessages([
                                'regex' => __('Use lowercase letters, numbers, and dashes only (e.g. drkarim).'),
                                'unique' => __('That slug is already taken by another tenant.'),
                                'not_in' => __('That slug is reserved by the platform. Pick another, e.g. the doctor’s name.'),
                            ])
                            ->disabled(fn (?string $operation) => $operation === 'edit')
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label(__('Display Name'))
                            ->extraInputAttributes(['name' => 'name'])
                            ->autocomplete('organization')
                            ->maxLength(255)
                            ->placeholder('e.g. Shefa Diagnostic Centre'),
                        TextInput::make('contact_phone')
                            ->label(__('Contact Phone'))
                            ->extraInputAttributes(['name' => 'contact_phone'])
                            ->autocomplete('tel')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->extraInputAttributes(['name' => 'whatsapp_number'])
                            ->autocomplete('tel')
                            ->placeholder('8801XXXXXXXXX')
                            ->maxLength(20),
                    ]),

                Fieldset::make(__('Owner login'))
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        TextInput::make('initial_owner_email')
                            ->label(__('Owner login email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('The founder — not necessarily a doctor. Their password is theirs; ChamberQ does not use this login.')),
                        TextInput::make('initial_owner_name')
                            ->label(__('Owner name on login'))
                            ->maxLength(255)
                            ->placeholder(fn (Get $get): string => (string) ($get('name') ?: 'Owner')),
                    ]),

                Fieldset::make(__('First doctor login'))
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        TextInput::make('initial_doctor_email')
                            ->label(__('Doctor login email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('Required so the practice can run the queue and consult screen — not only an owner account.')),
                        TextInput::make('initial_doctor_name')
                            ->label(__('Doctor name on login'))
                            ->maxLength(255)
                            ->placeholder(fn (Get $get): string => (string) ($get('name') ?: 'Dr.')),
                    ]),

                Fieldset::make(__('ChamberQ helper'))
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        TextInput::make('initial_helper_email')
                            ->label(__('Helper login email'))
                            ->email()
                            ->maxLength(255)
                            ->helperText(__('Leave blank for support@{slug}.chamberq.internal. Hidden from the owner’s Staff & Roles list.')),
                        TextInput::make('initial_helper_name')
                            ->label(__('Helper name on login'))
                            ->maxLength(255)
                            ->placeholder('ChamberQ Support'),
                    ]),

                Fieldset::make(__('Plan & Billing'))
                    ->schema([
                        Select::make('plan_tier')
                            ->label(__('Plan Tier'))
                            ->options([
                                'solo' => Tenant::planTierLabel('solo'),
                                'clinic' => Tenant::planTierLabel('clinic'),
                            ])
                            ->default('solo')
                            ->required()
                            ->live()
                            ->helperText(__('Maestro is one doctor. Clinic is the multi-doctor / lab sticker — unchecking modules does not lower Clinic price.')),
                        Select::make('slot_cap_mode')
                            ->label(__('Slot Cap Mode'))
                            ->helperText(__('Per session: each schedule has its own daily cap. Per doctor & chamber: all that doctor’s sessions at one chamber share one daily cap.'))
                            ->options([
                                'per_session' => 'Per Session',
                                'per_doctor_chamber' => 'Per Doctor & Chamber',
                            ])
                            ->default('per_session'),
                        TextInput::make('patient_booking_horizon_days')
                            ->label(__('Patient booking window (days)'))
                            ->helperText(fn (): string => __('How far ahead this Front door lets patients book. Leave empty to use the platform Booking window (:days days).', [
                                'days' => \App\Models\PlatformSetting::platformHorizonDays(),
                            ]))
                            ->numeric()
                            ->integer()
                            ->minValue(\App\Models\PlatformSetting::MIN_HORIZON_DAYS)
                            ->maxValue(\App\Models\PlatformSetting::MAX_HORIZON_DAYS)
                            ->nullable(),
                        Select::make('billing_status')
                            ->label(__('Billing Status'))
                            ->helperText(__('Past due, suspended, and read only close online booking. Site and admin stay open.'))
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Active',
                                'past_due' => 'Past Due (no new bookings)',
                                'suspended' => 'Suspended (no new bookings)',
                                'read_only' => 'Read Only (no new bookings)',
                            ])
                            ->default('trial')
                            ->required(),
                        TextInput::make('sms_balance')
                            ->label(__('SMS Credits'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(__('Prepaid confirmation credits. Top up with the header action on edit, or set here.'))
                            ->required(),
                    ]),

                Fieldset::make(__('Product modules'))
                    ->schema([
                        CheckboxList::make('product_modules')
                            ->label(__('Included for this client'))
                            ->helperText(fn (): string => app(PlanPricingService::class)->modulePriceHelperText())
                            ->options(Tenant::productModuleOptions())
                            ->default(Tenant::productModules())
                            ->columns(1)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Checkbox::make('module_stations')
                            ->label(__('Stations — rooms + split till'))
                            ->helperText(__('Opt-in clinic add-on: consult / visit / intervention rooms, catalogue fees, cash + mobile till with clinic vs doctor split. Not included in default modules.'))
                            ->default(false),
                        Checkbox::make('module_referrals')
                            ->label(__('Referrals — outside GP commissions'))
                            ->helperText(__('Track referring doctors. Visit / intervention / MSK cuts are set by the clinic in Branding Settings. Separate from ChamberQ marketer commissions.'))
                            ->default(false),
                        Checkbox::make('module_hr')
                            ->label(__('HR — staff attendance, leave, payroll'))
                            ->helperText(__('Employee roster, daily attendance, leave requests, salary payments linked to cashbook.'))
                            ->default(false),
                        Checkbox::make('module_pharmacy')
                            ->label(__('Pharmacy — counter sales + shop stock'))
                            ->helperText(__('Sell from the prescription (or walk-in), take cash/bKash at the counter, live quantity, physical count, pay the company now or after it sells. Default off — not in the Website/Queue/Rx bundle.'))
                            ->default(false),
                    ]),

                Fieldset::make(__('Launch offers'))
                    ->schema([
                        Checkbox::make('offer_prescription_lifetime_free')
                            ->label(__('Prescription free for life'))
                            ->helperText(__('Deadline 31 August — tick only if you are honouring it. Waives Prescription setup and monthly whenever Prescription is included. Super Admin can still tick after the date.'))
                            ->default(false)
                            ->live(),
                    ]),

                Fieldset::make(__('Referral & Discount'))
                    ->schema([
                        Select::make('marketer_id')
                            ->label(__('Marketer'))
                            ->relationship(
                                'marketer',
                                'display_name',
                                fn ($query) => $query->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live(),
                        Select::make('medical_representative_id')
                            ->label(__('Medical representative'))
                            ->relationship(
                                'medicalRepresentative',
                                'name',
                                fn ($query) => $query->where('is_active', true)
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->displayLabel())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->helperText(__('The pharma rep who brought this doctor. Leave empty for a direct sale.')),
                        Select::make('discount_code_id')
                            ->label(__('Discount code'))
                            ->relationship('discountCode', 'code')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live(),
                        TextInput::make('paying_setup_amount')
                            ->label(__('Paying one-time (৳)'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->live()
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) $state)
                            ->helperText(__('Courtesy price for this doctor. Empty = sticker after any coupon. Works for Maestro or modules.')),
                        TextInput::make('paying_monthly_amount')
                            ->label(__('Paying monthly (৳)'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->live()
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) $state),
                        Textarea::make('referral_note')
                            ->label(__('Referral note'))
                            ->helperText(__('e.g. Dr. Karim – Dhanmondi chamber'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Placeholder::make('pricing_preview')
                            ->label(__('Amount preview'))
                            ->content(function (Get $get): string {
                                return self::pricingPreviewContent($get);
                            })
                            ->columnSpanFull(),
                        TextInput::make('list_setup_amount')
                            ->label(__('List setup (snapshot)'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('setup_amount_due')
                            ->label(__('Setup due (snapshot)'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('monthly_amount_due')
                            ->label(__('Monthly due (snapshot)'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),

                Fieldset::make(__('Commission % (optional overrides)'))
                    ->schema([
                        TextInput::make('commission_setup_mr_rate')
                            ->label(__('Join — MR %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 20% with an MR, 0% direct.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state)),
                        TextInput::make('commission_setup_marketer_rate')
                            ->label(__('Join — marketer %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 20%.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state))
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    $mr = (float) (DealCommissionRates::percentToRate($get('commission_setup_mr_rate')) ?? 0);
                                    $mk = (float) (DealCommissionRates::percentToRate($value) ?? 0);
                                    if (round($mr + $mk, 4) > 1) {
                                        $fail(__('MR and marketer join cuts cannot exceed 100%.'));
                                    }
                                },
                            ]),
                        TextInput::make('commission_year1_prepaid_mr_rate')
                            ->label(__('Year 1 prepaid — MR %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 15% with an MR, 0% direct.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state)),
                        TextInput::make('commission_year1_prepaid_marketer_rate')
                            ->label(__('Year 1 prepaid — marketer %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 5% with an MR, 20% direct.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state)),
                        TextInput::make('commission_year2_mr_rate')
                            ->label(__('Year 2+ — MR %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 5% with an MR, 0% direct. Year 1 month-by-month is always 0%.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state)),
                        TextInput::make('commission_year2_marketer_rate')
                            ->label(__('Year 2+ — marketer %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live()
                            ->helperText(__('Empty = 5% with an MR, 10% direct.'))
                            ->formatStateUsing(fn ($state) => DealCommissionRates::rateToPercent($state))
                            ->dehydrateStateUsing(fn ($state) => DealCommissionRates::percentToRate($state)),
                    ])
                    ->columns(2),

                Fieldset::make(__('Appearance & Locale'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Theme Color'))
                            ->default(Tenant::DEFAULT_THEME_COLOR)
                            ->regex('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),
                        Select::make('default_locale')
                            ->label(__('Default Locale'))
                            ->helperText(__('System UI default (book/ticket/portal). Homepage Bangla requires the bangla_homepage feature flag.'))
                            ->options([
                                'en' => 'English',
                                'bn' => 'বাংলা (Bengali)',
                            ])
                            ->default('en'),
                    ]),

                Fieldset::make(__('Feature Overrides'))
                    ->schema([
                        KeyValue::make('feature_flags')
                            ->label(__('Feature Flags'))
                            ->helperText(__('Paid add-ons and size overrides only (not the modules above). Example: bangla_homepage = true. Other keys: lab_tests, multiple_doctors, multiple_chambers. Stations is toggled separately above.'))
                            ->keyLabel('Feature')
                            ->valueLabel('Enabled (true/false)')
                            ->keyPlaceholder('e.g. bangla_homepage')
                            ->valuePlaceholder('true')
                            ->columnSpanFull(),
                    ]),

                Repeater::make('domains')
                    ->relationship('domains')
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('Custom domain (optional)')
                            ->helperText(__('e.g. drkarim.com — DNS must point to your server. Leave empty to use only /{slug} on the platform domain.')),
                    ])
                    ->columnSpanFull()
                    ->helperText(__('Custom domains serve the tenant at the site root (/book). The platform path always works at /{slug} using the URL slug above.')),
            ]);
    }

    public static function pricingPreviewContent(Get $get): string
    {
        $tier = (string) ($get('plan_tier') ?: 'solo');
        $modules = $get('product_modules');
        if (! is_array($modules) || $modules === []) {
            $modules = Tenant::productModules();
        }

        $code = $get('discount_code_id')
            ? DiscountCode::find($get('discount_code_id'))
            : null;

        $payingSetup = $get('paying_setup_amount');
        $payingMonthly = $get('paying_monthly_amount');

        $commissions = app(CommissionService::class);
        $amounts = $commissions->quoteAmounts(
            $tier,
            $modules,
            $code,
            filter_var($get('offer_prescription_lifetime_free'), FILTER_VALIDATE_BOOLEAN),
            $payingSetup !== null && $payingSetup !== '' ? (int) $payingSetup : null,
            $payingMonthly !== null && $payingMonthly !== '' ? (int) $payingMonthly : null,
        );

        $lines = [
            sprintf(
                'List: setup ৳%s / monthly ৳%s → Paying: setup ৳%s / monthly ৳%s',
                number_format($amounts['list_setup']),
                number_format($amounts['list_monthly']),
                number_format($amounts['setup_due']),
                number_format($amounts['monthly_due']),
            ),
        ];

        $scratch = new Tenant([
            'marketer_id' => $get('marketer_id') ?: null,
            'medical_representative_id' => $get('medical_representative_id') ?: null,
            'commission_setup_mr_rate' => DealCommissionRates::percentToRate($get('commission_setup_mr_rate')),
            'commission_setup_marketer_rate' => DealCommissionRates::percentToRate($get('commission_setup_marketer_rate')),
            'commission_year1_prepaid_mr_rate' => DealCommissionRates::percentToRate($get('commission_year1_prepaid_mr_rate')),
            'commission_year1_prepaid_marketer_rate' => DealCommissionRates::percentToRate($get('commission_year1_prepaid_marketer_rate')),
            'commission_year2_mr_rate' => DealCommissionRates::percentToRate($get('commission_year2_mr_rate')),
            'commission_year2_marketer_rate' => DealCommissionRates::percentToRate($get('commission_year2_marketer_rate')),
        ]);

        $preview = $commissions->previewCommission(
            $scratch->marketer_id ? (int) $scratch->marketer_id : null,
            $amounts['setup_due'],
            $amounts['monthly_due'],
            $scratch->medical_representative_id ? (int) $scratch->medical_representative_id : null,
            $scratch,
        );

        if ($preview === null) {
            $lines[] = 'No partner attached — no commission.';
        } else {
            $mrName = $scratch->medical_representative_id
                ? (MedicalRepresentative::find($scratch->medical_representative_id)?->name ?? 'MR')
                : null;
            $joinBits = [];
            if ($mrName && $preview['mr_setup_commission'] > 0) {
                $joinBits[] = sprintf(
                    'MR %s ৳%s (%s%%)',
                    $mrName,
                    number_format($preview['mr_setup_commission']),
                    round($preview['mr_setup_rate'] * 100),
                );
            }
            if ($scratch->marketer_id) {
                $joinBits[] = sprintf(
                    'Marketer %s ৳%s (%s%%)',
                    $preview['display_name'],
                    number_format($preview['setup_commission']),
                    round($preview['setup_rate'] * 100),
                );
            }
            $chamberqJoin = $amounts['setup_due'] - $preview['setup_commission'] - $preview['mr_setup_commission'];
            $joinBits[] = 'ChamberQ ৳'.number_format(max(0, $chamberqJoin));
            $lines[] = 'Join: '.implode(' · ', $joinBits);

            $yearBits = [];
            if ($preview['year1_prepaid_mr_commission'] > 0) {
                $yearBits[] = sprintf('MR ৳%s', number_format($preview['year1_prepaid_mr_commission']));
            }
            if ($preview['year1_prepaid_marketer_commission'] > 0) {
                $yearBits[] = sprintf('Marketer ৳%s', number_format($preview['year1_prepaid_marketer_commission']));
            }
            $lines[] = 'Year 1 month-by-month: no partner cut. Year 1 if paid now: '.($yearBits !== [] ? implode(' · ', $yearBits) : 'none').'.';
            $lines[] = sprintf(
                'Year 2+ each month: MR ৳%s · marketer ৳%s',
                number_format($preview['year2_mr_commission']),
                number_format($preview['year2_marketer_commission']),
            );
        }

        return implode(' — ', $lines);
    }
}
