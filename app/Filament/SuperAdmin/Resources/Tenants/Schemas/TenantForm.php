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
                            ->maxLength(255)
                            ->placeholder('e.g. Shefa Diagnostic Centre'),
                        TextInput::make('contact_phone')
                            ->label(__('Contact Phone'))
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
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
                            ->options([
                                'per_session' => 'Per Session',
                                'per_doctor_chamber' => 'Per Doctor & Chamber',
                            ])
                            ->default('per_session'),
                        Select::make('billing_status')
                            ->label(__('Billing Status'))
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Active',
                                'past_due' => 'Past Due',
                                'suspended' => 'Suspended',
                            ])
                            ->default('trial'),
                    ]),

                Fieldset::make(__('Appearance & Locale'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Theme Color')),
                        Select::make('default_locale')
                            ->label(__('Default Locale'))
                            ->options([
                                'en' => 'English',
                                'bn' => 'বাংলা (Bengali)',
                            ])
                            ->default('bn'),
                    ]),

                Fieldset::make(__('Feature Overrides'))
                    ->schema([
                        KeyValue::make('feature_flags')
                            ->label(__('Feature Flags'))
                            ->keyLabel('Feature')
                            ->valueLabel('Enabled (true/false)')
                            ->keyPlaceholder('e.g. lab_tests')
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
