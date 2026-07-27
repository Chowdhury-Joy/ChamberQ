<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots\Schemas;

use App\Support\DayOfWeek;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LabCollectionSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chamber_id')
                    ->relationship('chamber', 'name'),
                Select::make('day_of_week')
                    ->required()
                    ->options(DayOfWeek::options()),
                TimePicker::make('start_time')
                    ->required()
                    ->seconds(false),
                TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
                    ->rule(function (Get $get) {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $start = $get('start_time');
                            if (blank($start) || blank($value)) {
                                return;
                            }
                            if ((string) $value <= (string) $start) {
                                $fail(__('End time must be after start time.'));
                            }
                        };
                    }),
                TextInput::make('slot_cap')
                    ->required()
                    ->numeric()
                    ->minValue(1),
            ]);
    }
}
