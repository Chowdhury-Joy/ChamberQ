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
                TextInput::make('chamber_id')
                    ->required()
                    ->numeric(),
                TextInput::make('doctor_id')
                    ->required()
                    ->numeric(),
                Select::make('day_of_week')
                    ->required()
                    ->options(DayOfWeek::options()),
                TextInput::make('session_name')
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
