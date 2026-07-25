<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('id')
                    ->label('Tenant ID (e.g., demo)')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Select::make('slot_cap_mode')
                    ->options([
                        'per_session' => 'Per Session',
                        'per_doctor_chamber' => 'Per Doctor & Chamber',
                    ])
                    ->default('per_session'),
                \Filament\Forms\Components\Repeater::make('domains')
                    ->relationship('domains')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('domain')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('Domain (e.g., demo.localhost)'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
