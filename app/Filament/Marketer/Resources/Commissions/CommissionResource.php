<?php

namespace App\Filament\Marketer\Resources\Commissions;

use App\Filament\Marketer\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Marketer\Resources\Commissions\Tables\CommissionsTable;
use App\Models\Commission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Commission History';

    public static function getEloquentQuery(): Builder
    {
        $marketerId = auth()->user()?->marketerProfile?->id;

        return parent::getEloquentQuery()
            ->when($marketerId, fn (Builder $q) => $q->where('marketer_id', $marketerId), fn (Builder $q) => $q->whereRaw('1 = 0'));
    }

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
