<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Pages;

use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSlotBlock extends EditRecord
{
    protected static string $resource = SlotBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
