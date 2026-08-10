<?php

namespace App\Filament\TenantAdmin\Resources\Departments\Pages;

use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;
}
