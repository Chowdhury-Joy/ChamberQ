<?php

namespace App\Filament\TenantAdmin\Resources\Departments\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = DepartmentResource::class;
}
