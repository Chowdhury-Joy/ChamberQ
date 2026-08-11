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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->canManageOps() ?? false)
            && (tenant()?->hasFeature('lab_tests') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return LabCollectionSlotForm::configure($schema);
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
