<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages;

use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\MedicalRepresentativeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicalRepresentative extends CreateRecord
{
    protected static string $resource = MedicalRepresentativeResource::class;
}
