<?php

namespace App\Filament\SuperAdmin\Resources\Commissions;

use App\Filament\SuperAdmin\Resources\Commissions\Pages\ListCommissions;
use App\Filament\SuperAdmin\Resources\Commissions\Tables\CommissionsTable;
use App\Models\Commission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Commissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return CommissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
