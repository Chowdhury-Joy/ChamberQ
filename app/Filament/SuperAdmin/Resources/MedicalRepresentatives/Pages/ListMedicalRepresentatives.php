<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages;

use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\MedicalRepresentativeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicalRepresentatives extends ListRecords
{
    protected static string $resource = MedicalRepresentativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
