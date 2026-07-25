<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
