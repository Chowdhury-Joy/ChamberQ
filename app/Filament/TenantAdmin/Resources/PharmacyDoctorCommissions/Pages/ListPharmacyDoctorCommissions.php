<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyDoctorCommissions\Pages;

use App\Filament\TenantAdmin\Resources\PharmacyDoctorCommissions\PharmacyDoctorCommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPharmacyDoctorCommissions extends ListRecords
{
    protected static string $resource = PharmacyDoctorCommissionResource::class;
}
