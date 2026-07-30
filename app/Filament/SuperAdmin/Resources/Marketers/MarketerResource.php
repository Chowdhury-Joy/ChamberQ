<?php

namespace App\Filament\SuperAdmin\Resources\Marketers;

use App\Filament\SuperAdmin\Resources\Marketers\Pages\CreateMarketer;
use App\Filament\SuperAdmin\Resources\Marketers\Pages\EditMarketer;
use App\Filament\SuperAdmin\Resources\Marketers\Pages\ListMarketers;
use App\Filament\SuperAdmin\Resources\Marketers\Schemas\MarketerForm;
use App\Filament\SuperAdmin\Resources\Marketers\Tables\MarketersTable;
use App\Models\Marketer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MarketerResource extends Resource
{
    protected static ?string $model = Marketer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Marketers';

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    public static function form(Schema $schema): Schema
    {
        return MarketerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketers::route('/'),
            'create' => CreateMarketer::route('/create'),
            'edit' => EditMarketer::route('/{record}/edit'),
        ];
    }
}
