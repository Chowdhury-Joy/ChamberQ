<?php

namespace App\Filament\TenantAdmin\Resources\Patients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->tel()
                ->required()
                ->maxLength(20)
                ->rule('regex:/^01[3-9]\d{8}$/'),
            DatePicker::make('date_of_birth')
                ->label(__('Date of birth'))
                ->maxDate(now()),
            TextInput::make('age')
                ->numeric()
                ->minValue(0)
                ->maxValue(120),
            DatePicker::make('age_recorded_at')
                ->label(__('Age recorded on'))
                ->maxDate(now()),
            Select::make('sex')
                ->options([
                    'male' => __('Male'),
                    'female' => __('Female'),
                    'other' => __('Other'),
                ]),
            Textarea::make('allergies')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('conditions')
                ->label(__('Ongoing conditions'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('medicines')
                ->label(__('Current medicines'))
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }
}
