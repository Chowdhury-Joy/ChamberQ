<?php

namespace App\Filament\TenantAdmin\Resources\LabTests\Pages;

use App\Filament\TenantAdmin\Resources\LabTests\LabTestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLabTests extends ListRecords
{
    protected static string $resource = LabTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
