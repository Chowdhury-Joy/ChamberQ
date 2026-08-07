<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Schemas;

use App\Models\Doctor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Toggle::make('staff_may_enter_prescriptions')
                    ->label(__('Staff may type this doctor\'s prescriptions'))
                    ->helperText(__('For doctors who write on paper as usual and let staff key it in afterwards. Staff get the medicine list and follow-up only — never the diagnosis, voice notes or past visits. Off by default.'))
                    ->default(false),
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
