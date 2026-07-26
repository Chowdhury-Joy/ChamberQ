<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Schemas;

use App\Support\DayOfWeek;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ScheduleSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chamber_id')
                    ->relationship('chamber', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('doctor_id')
                    ->relationship('doctor', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('day_of_week')
                    ->required()
                    ->options(DayOfWeek::options()),
                TextInput::make('session_name')
                    ->required()
                    ->maxLength(255),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TextInput::make('slot_cap')
                    ->required()
                    ->numeric()
                    ->minValue(1),
            ]);
    }
}
