<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SlotBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_id')
                    ->required(),
                TextInput::make('chamber_id')
                    ->numeric(),
                TextInput::make('doctor_id')
                    ->numeric(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('reason'),
            ]);
    }
}
