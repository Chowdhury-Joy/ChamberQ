<?php

namespace App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages;

use App\Filament\TenantAdmin\Resources\FeeCatalogItems\FeeCatalogItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeeCatalogItem extends EditRecord
{
    protected static string $resource = FeeCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
