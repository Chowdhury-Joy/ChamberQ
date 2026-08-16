<?php

namespace App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages;

use App\Filament\TenantAdmin\Resources\FeeCatalogItems\FeeCatalogItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeeCatalogItem extends CreateRecord
{
    protected static string $resource = FeeCatalogItemResource::class;
}
