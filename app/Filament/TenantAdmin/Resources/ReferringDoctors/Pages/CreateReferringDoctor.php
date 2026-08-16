<?php

namespace App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages;

use App\Filament\TenantAdmin\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferringDoctor extends CreateRecord
{
    protected static string $resource = ReferringDoctorResource::class;
}
