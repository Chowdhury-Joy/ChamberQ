<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Pages;

use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotBlocks extends ListRecords
{
    protected static string $resource = SlotBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
