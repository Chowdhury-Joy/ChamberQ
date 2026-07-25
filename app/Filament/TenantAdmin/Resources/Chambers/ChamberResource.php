<?php

namespace App\Filament\TenantAdmin\Resources\Chambers;

use App\Filament\TenantAdmin\Resources\Chambers\Pages\CreateChamber;
use App\Filament\TenantAdmin\Resources\Chambers\Pages\EditChamber;
use App\Filament\TenantAdmin\Resources\Chambers\Pages\ListChambers;
use App\Filament\TenantAdmin\Resources\Chambers\Schemas\ChamberForm;
use App\Filament\TenantAdmin\Resources\Chambers\Tables\ChambersTable;
use App\Models\Chamber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChamberResource extends Resource
{
    protected static ?string $model = Chamber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ChamberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChambersTable::configure($table);
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
            'index' => ListChambers::route('/'),
            'create' => CreateChamber::route('/create'),
            'edit' => EditChamber::route('/{record}/edit'),
        ];
    }
}
