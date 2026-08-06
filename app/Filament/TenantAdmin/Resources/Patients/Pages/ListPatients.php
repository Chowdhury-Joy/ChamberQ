<?php

namespace App\Filament\TenantAdmin\Resources\Patients\Pages;

use App\Filament\TenantAdmin\Resources\Patients\PatientResource;
use Filament\Resources\Pages\ListRecords;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;
}
