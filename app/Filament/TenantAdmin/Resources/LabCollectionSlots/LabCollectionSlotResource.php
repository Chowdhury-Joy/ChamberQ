<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots;

use App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages\CreateLabCollectionSlot;
use App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages\EditLabCollectionSlot;
use App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages\ListLabCollectionSlots;
use App\Filament\TenantAdmin\Resources\LabCollectionSlots\Schemas\LabCollectionSlotForm;
use App\Filament\TenantAdmin\Resources\LabCollectionSlots\Tables\LabCollectionSlotsTable;
use App\Models\LabCollectionSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LabCollectionSlotResource extends Resource
{
    protected static ?string $model = LabCollectionSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('chamber_id')
                ->relationship('chamber', 'name'),
            Forms\Components\Select::make('day_of_week')
                ->required()
                ->options([
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday',
                ]),
            Forms\Components\TimePicker::make('start_time')
                ->required(),
            Forms\Components\TimePicker::make('end_time')
                ->required(),
            Forms\Components\TextInput::make('slot_cap')
                ->required()
                ->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return LabCollectionSlotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabCollectionSlots::route('/'),
            'create' => CreateLabCollectionSlot::route('/create'),
            'edit' => EditLabCollectionSlot::route('/{record}/edit'),
        ];
    }
}
