<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyItems\Pages;

use App\Filament\TenantAdmin\Resources\PharmacyItems\PharmacyItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPharmacyItems extends ListRecords
{
    protected static string $resource = PharmacyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
