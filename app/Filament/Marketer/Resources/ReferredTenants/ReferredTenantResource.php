<?php

namespace App\Filament\Marketer\Resources\ReferredTenants;

use App\Filament\Marketer\Resources\ReferredTenants\Pages\ListReferredTenants;
use App\Filament\Marketer\Resources\ReferredTenants\Tables\ReferredTenantsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReferredTenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Referred Doctors';

    protected static ?string $modelLabel = 'Referred doctor';

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
        return ReferredTenantsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferredTenants::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
