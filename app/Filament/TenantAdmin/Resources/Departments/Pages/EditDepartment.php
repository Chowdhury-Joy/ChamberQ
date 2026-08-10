<?php

namespace App\Filament\TenantAdmin\Resources\Departments\Pages;

use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;
}
