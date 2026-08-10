<?php

namespace App\Filament\TenantAdmin\Resources\Departments;

use App\Filament\TenantAdmin\Resources\Concerns\ClinicWebsiteResource;
use App\Filament\TenantAdmin\Resources\Departments\Pages\CreateDepartment;
use App\Filament\TenantAdmin\Resources\Departments\Pages\EditDepartment;
use App\Filament\TenantAdmin\Resources\Departments\Pages\ListDepartments;
use App\Filament\TenantAdmin\Resources\Departments\Schemas\DepartmentForm;
use App\Filament\TenantAdmin\Resources\Departments\Tables\DepartmentsTable;
use App\Models\Department;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use ClinicWebsiteResource;

    protected static ?string $model = Department::class;

    protected static ?string $navigationLabel = 'Departments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
