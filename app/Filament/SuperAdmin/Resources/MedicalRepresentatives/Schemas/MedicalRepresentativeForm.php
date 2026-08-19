<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class MedicalRepresentativeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make(__('Medical representative'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('company')
                            ->label(__('Company / pharma house'))
                            ->maxLength(255),
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
