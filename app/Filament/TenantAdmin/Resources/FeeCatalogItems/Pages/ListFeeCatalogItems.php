<?php

namespace App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages;

use App\Filament\TenantAdmin\Resources\FeeCatalogItems\FeeCatalogItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeCatalogItems extends ListRecords
{
    protected static string $resource = FeeCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
