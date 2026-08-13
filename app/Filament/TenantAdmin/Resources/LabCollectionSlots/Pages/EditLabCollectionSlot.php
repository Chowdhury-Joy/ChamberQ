<?php

namespace App\Filament\TenantAdmin\Resources\LabCollectionSlots\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\LabCollectionSlots\LabCollectionSlotResource;
use Filament\Resources\Pages\EditRecord;

class EditLabCollectionSlot extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = LabCollectionSlotResource::class;
}
