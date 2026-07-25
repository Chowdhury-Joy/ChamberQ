<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages;

use App\Filament\TenantAdmin\Resources\LabCollectionSlots\LabCollectionSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLabCollectionSlots extends ListRecords
{
    protected static string $resource = LabCollectionSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
