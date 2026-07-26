<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChamberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->columnSpanFull()
                    ->maxLength(500),
                TextInput::make('latitude')
                    ->numeric()
                    ->rule('between:-90,90'),
                TextInput::make('longitude')
                    ->numeric()
                    ->rule('between:-180,180'),
                KeyValue::make('hours')
                    ->label(__('Operating Hours'))
                    ->keyLabel(__('Day'))
                    ->valueLabel(__('Hours'))
                    ->keyPlaceholder('e.g. Saturday')
                    ->valuePlaceholder('e.g. 09:00–17:00')
                    ->columnSpanFull(),
                TextInput::make('contact')
                    ->tel()
                    ->maxLength(20),
            ]);
    }
}
