<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Pages;

use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChamber extends EditRecord
{
    protected static string $resource = ChamberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
