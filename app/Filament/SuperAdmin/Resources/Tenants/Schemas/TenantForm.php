<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use App\Models\DiscountCode;
use App\Services\DiscountCalculator;
use App\Services\PlanPricingService;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->alphaDash()
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

                Fieldset::make(__('Plan & Billing'))
                    ->schema([
                        Select::make('plan_tier')
                            ->label(__('Plan Tier'))
                            ->options([
                                'solo' => 'Solo Doctor',
                                'clinic' => 'Clinic',
                            ])
                            ->default('solo')
                            ->required(),
                        Select::make('slot_cap_mode')
                            ->label(__('Slot Cap Mode'))
                            ->helperText(__('Per session: each schedule has its own daily cap. Per doctor & chamber: all that doctor’s sessions at one chamber share one daily cap.'))
                            ->options([
                                'per_session' => 'Per Session',
                                'per_doctor_chamber' => 'Per Doctor & Chamber',
                            ])
                            ->default('per_session'),
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
                        Select::make('discount_code_id')
                            ->label(__('Discount code'))
                            ->relationship('discountCode', 'code')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live(),
                        Textarea::make('referral_note')
                            ->label(__('Referral note'))
                            ->helperText(__('e.g. Dr. Karim – Dhanmondi chamber'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Placeholder::make('pricing_preview')
                            ->label(__('Amount preview'))
                            ->content(function (Get $get): string {
                                $tier = (string) ($get('plan_tier') ?: 'solo');
                                $list = app(PlanPricingService::class)->listPricesForTier($tier);
                                $code = $get('discount_code_id')
                                    ? DiscountCode::find($get('discount_code_id'))
                                    : null;
                                $amounts = app(DiscountCalculator::class)->calculate(
                                    $list['setup'],
                                    $list['monthly'],
                                    $code
                                );

                                return sprintf(
                                    'List: setup ৳%s / monthly ৳%s → Due: setup ৳%s / monthly ৳%s',
                                    number_format($amounts['list_setup']),
                                    number_format($amounts['list_monthly']),
                                    number_format($amounts['setup_due']),
                                    number_format($amounts['monthly_due']),
                                );
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

                Fieldset::make(__('Appearance & Locale'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Theme Color'))
                            ->default(\App\Models\Tenant::DEFAULT_THEME_COLOR),
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
                            ->helperText(__('Paid add-on example: bangla_homepage = true. Other keys: lab_tests, multiple_doctors, multiple_chambers.'))
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
}
