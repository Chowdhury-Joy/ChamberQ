<?php

namespace App\Filament\SuperAdmin\Resources\Marketers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class MarketerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make(__('Login account'))
                    ->schema([
                        TextInput::make('user_name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->dehydrated(false),
                        TextInput::make('user_email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->dehydrated(false),
                        TextInput::make('user_password')
                            ->label(__('Password'))
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->maxLength(255),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create'),

                Fieldset::make(__('Partner profile'))
                    ->schema([
                        TextInput::make('display_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label(__('Referral code'))
                            ->helperText(__('Used in ?ref= links, e.g. joy20'))
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('payout_account')
                            ->label(__('bKash / payout number'))
                            ->maxLength(100),
                        TextInput::make('setup_commission_rate')
                            ->label(__('Setup commission rate'))
                            ->numeric()
                            ->default(0.20)
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->helperText(__('0.20 = 20%')),
                        TextInput::make('monthly_commission_rate')
                            ->label(__('Monthly commission rate'))
                            ->numeric()
                            ->default(0.10)
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->helperText(__('0.10 = 10%')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
