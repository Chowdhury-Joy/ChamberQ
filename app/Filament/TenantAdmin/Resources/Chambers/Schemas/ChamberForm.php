<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ChamberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('latitude'),
                TextInput::make('longitude'),
                Textarea::make('hours')
                    ->columnSpanFull(),
                TextInput::make('contact'),
            ]);
    }
}
