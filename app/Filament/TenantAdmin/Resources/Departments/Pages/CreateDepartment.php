<?php

namespace App\Filament\TenantAdmin\Resources\Departments\Pages;

use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
