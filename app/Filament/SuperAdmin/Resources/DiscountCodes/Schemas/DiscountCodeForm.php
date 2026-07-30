<?php

namespace App\Filament\SuperAdmin\Resources\DiscountCodes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make(__('Code'))
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null),
                        TextInput::make('label')
                            ->maxLength(255),
                        Select::make('marketer_id')
                            ->label(__('Tied to marketer'))
                            ->relationship('marketer', 'display_name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                Fieldset::make(__('Discount (percent only in v1)'))
                    ->schema([
                        TextInput::make('setup_percent')
                            ->label(__('Setup discount %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->nullable(),
                        TextInput::make('monthly_percent')
                            ->label(__('Monthly discount %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->nullable(),
                    ]),
                Fieldset::make(__('Limits'))
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->nullable(),
                        DateTimePicker::make('ends_at')
                            ->nullable(),
                        TextInput::make('max_redemptions')
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),
                        TextInput::make('redemption_count')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),
            ]);
    }
}
