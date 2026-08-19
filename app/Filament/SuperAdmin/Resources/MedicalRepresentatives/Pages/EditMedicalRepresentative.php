<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Pages;

use App\Filament\SuperAdmin\Resources\MedicalRepresentatives\MedicalRepresentativeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicalRepresentative extends EditRecord
{
    protected static string $resource = MedicalRepresentativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
