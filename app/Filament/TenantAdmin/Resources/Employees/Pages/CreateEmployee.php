<?php

namespace App\Filament\TenantAdmin\Resources\Employees\Pages;

use App\Filament\TenantAdmin\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
