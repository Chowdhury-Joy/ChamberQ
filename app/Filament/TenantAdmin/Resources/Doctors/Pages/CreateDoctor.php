<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Pages;

use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;
}
