<?php

namespace App\Filament\TenantAdmin\Resources\Patients\Pages;

use App\Filament\TenantAdmin\Resources\Patients\PatientResource;
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;
}
