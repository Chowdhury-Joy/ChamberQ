<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                            ->label('Tenant ID (slug, e.g. demo)')
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
                            ->label('Domain (e.g. demo.localhost)'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
