<?php

namespace App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages;

use App\Filament\TenantAdmin\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferringDoctors extends ListRecords
{
    protected static string $resource = ReferringDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
