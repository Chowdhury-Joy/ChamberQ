<?php

namespace App\Filament\TenantAdmin\Resources\Patients;

use App\Filament\TenantAdmin\Resources\Patients\Pages\EditPatient;
use App\Filament\TenantAdmin\Resources\Patients\Pages\ListPatients;
use App\Filament\TenantAdmin\Resources\Patients\Schemas\PatientForm;
use App\Filament\TenantAdmin\Resources\Patients\Tables\PatientsTable;
use App\Models\Patient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Patients';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 25;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageOps() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PatientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'edit' => EditPatient::route('/{record}/edit'),
        ];
    }
}
