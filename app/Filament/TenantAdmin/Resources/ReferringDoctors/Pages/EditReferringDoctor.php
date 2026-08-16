<?php

namespace App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages;

use App\Filament\TenantAdmin\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferringDoctor extends EditRecord
{
    protected static string $resource = ReferringDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
