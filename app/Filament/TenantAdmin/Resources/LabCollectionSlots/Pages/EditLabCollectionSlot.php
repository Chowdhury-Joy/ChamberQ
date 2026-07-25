<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages;

use App\Filament\TenantAdmin\Resources\LabCollectionSlots\LabCollectionSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLabCollectionSlot extends EditRecord
{
    protected static string $resource = LabCollectionSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
