<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use App\Models\DiscountCode;
use App\Models\Tenant;
use App\Services\CommissionService;
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
                    ]),

                Fieldset::make(__('Launch offers'))
                    ->schema([
                        Checkbox::make('offer_prescription_lifetime_free')
                            ->label(__('Prescription free for life'))
                            ->helperText(__('Deadline 31 August — tick only if you are honouring it. Waives Prescription setup and monthly whenever Prescription is included. Super Admin can still tick after the date.'))
                            ->default(false)
                            ->live(),
                        Checkbox::make('offer_prepaid_year_setup')
                            ->label(__('Prepaid year — 50% off setup'))
                            ->helperText(__('Deadline 30 September — tick only if they confirmed a year. Halves setup after other discounts. Confirm the twelve months with the header action after setup is paid.'))
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

                Fieldset::make(__('Appearance & Locale'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Theme Color'))
                            ->default(Tenant::DEFAULT_THEME_COLOR),
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
                            ->helperText(__('Paid add-ons and size overrides only (not the modules above). Example: bangla_homepage = true. Other keys: lab_tests, multiple_doctors, multiple_chambers.'))
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

        $commissions = app(CommissionService::class);
        $amounts = $commissions->quoteAmounts(
            $tier,
            $modules,
            $code,
            filter_var($get('offer_prescription_lifetime_free'), FILTER_VALIDATE_BOOLEAN),
            filter_var($get('offer_prepaid_year_setup'), FILTER_VALIDATE_BOOLEAN),
        );

        $lines = [
            sprintf(
                'List: setup ৳%s / monthly ৳%s → Due: setup ৳%s / monthly ৳%s',
                number_format($amounts['list_setup']),
                number_format($amounts['list_monthly']),
                number_format($amounts['setup_due']),
                number_format($amounts['monthly_due']),
            ),
        ];

        $marketerId = $get('marketer_id');
        $preview = $commissions->previewCommission(
            $marketerId !== null && $marketerId !== '' ? (int) $marketerId : null,
            $amounts['setup_due'],
            $amounts['monthly_due'],
        );

        if ($preview === null) {
            $lines[] = 'No partner attached — no commission.';
        } else {
            $lines[] = sprintf(
                'Partner %s (%s%% / %s%%): setup commission ৳%s · monthly ৳%s',
                $preview['display_name'],
                round($preview['setup_rate'] * 100),
                round($preview['monthly_rate'] * 100),
                number_format($preview['setup_commission']),
                number_format($preview['monthly_commission']),
            );
        }

        return implode(' — ', $lines);
    }
}
