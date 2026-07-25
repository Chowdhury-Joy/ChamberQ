<?php

namespace App\Filament\TenantAdmin\Resources\LabTests;

use App\Filament\TenantAdmin\Resources\LabTests\Pages\CreateLabTest;
use App\Filament\TenantAdmin\Resources\LabTests\Pages\EditLabTest;
use App\Filament\TenantAdmin\Resources\LabTests\Pages\ListLabTests;
use App\Filament\TenantAdmin\Resources\LabTests\Schemas\LabTestForm;
use App\Filament\TenantAdmin\Resources\LabTests\Tables\LabTestsTable;
use App\Models\LabTest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;

class LabTestResource extends Resource
{
    protected static ?string $model = LabTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('price')
                ->required()
                ->numeric()
                ->prefix('$'),
            Forms\Components\TextInput::make('turnaround_time')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table;
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
            'index' => ListLabTests::route('/'),
            'create' => CreateLabTest::route('/create'),
            'edit' => EditLabTest::route('/{record}/edit'),
        ];
    }
}
