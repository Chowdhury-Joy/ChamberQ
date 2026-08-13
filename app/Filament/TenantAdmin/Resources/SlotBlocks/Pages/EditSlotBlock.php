<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use Filament\Resources\Pages\EditRecord;

class EditSlotBlock extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = SlotBlockResource::class;
}
