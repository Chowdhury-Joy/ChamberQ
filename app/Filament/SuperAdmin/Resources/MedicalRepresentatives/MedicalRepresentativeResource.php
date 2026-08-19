<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives;

use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages\CreateMedicalRepresentative;
use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages\EditMedicalRepresentative;
use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages\ListMedicalRepresentatives;
use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Schemas\MedicalRepresentativeForm;
use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Tables\MedicalRepresentativesTable;
use App\Models\MedicalRepresentative;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MedicalRepresentativeResource extends Resource
{
    protected static ?string $model = MedicalRepresentative::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Medical representatives';

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    public static function form(Schema $schema): Schema
    {
        return MedicalRepresentativeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalRepresentativesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalRepresentatives::route('/'),
            'create' => CreateMedicalRepresentative::route('/create'),
            'edit' => EditMedicalRepresentative::route('/{record}/edit'),
        ];
    }
}
