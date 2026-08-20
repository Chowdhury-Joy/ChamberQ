<?php

namespace App\Filament\TenantAdmin\Resources\Doctors;

use App\Filament\TenantAdmin\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\TenantAdmin\Resources\Doctors\Pages\EditDoctor;
use App\Filament\TenantAdmin\Resources\Doctors\Pages\ListDoctors;
use App\Filament\TenantAdmin\Resources\Doctors\Schemas\DoctorForm;
use App\Filament\TenantAdmin\Resources\Doctors\Tables\DoctorsTable;
use App\Models\Doctor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DoctorResource extends Resource
{
    protected static ?string $model = Doctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Doctors';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageOps() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageOps() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return DoctorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorsTable::configure($table);
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
            'index' => ListDoctors::route('/'),
            'create' => CreateDoctor::route('/create'),
            'edit' => EditDoctor::route('/{record}/edit'),
        ];
    }
}
