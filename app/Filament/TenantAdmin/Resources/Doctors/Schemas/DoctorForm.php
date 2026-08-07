<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Schemas;

use App\Models\Doctor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->extraInputAttributes(['name' => 'name'])
                    ->autocomplete('name')
                    ->required(),
                Select::make('practice_type')
                    ->label(__('Practice type'))
                    ->options(Doctor::practiceTypeOptions())
                    ->default(Doctor::PRACTICE_GENERAL)
                    ->required()
                    ->native(false),
                TextInput::make('qualifications')
                    ->label(__('Qualifications'))
                    ->placeholder(__('e.g. MBBS, FCPS (Medicine)'))
                    ->maxLength(255),
                TextInput::make('registration_number')
                    ->label(__('BM&DC registration number'))
                    ->maxLength(80),
            ]);
    }
}
