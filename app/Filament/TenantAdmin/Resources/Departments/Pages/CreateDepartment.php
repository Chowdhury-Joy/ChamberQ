<?php

namespace App\Filament\TenantAdmin\Resources\Departments\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = DepartmentResource::class;
}
