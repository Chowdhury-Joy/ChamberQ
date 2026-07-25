<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class LabCollectionSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_id')
                    ->required(),
                TextInput::make('chamber_id')
                    ->numeric(),
                TextInput::make('day_of_week')
                    ->required(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TextInput::make('slot_cap')
                    ->required()
                    ->numeric(),
            ]);
    }
}
