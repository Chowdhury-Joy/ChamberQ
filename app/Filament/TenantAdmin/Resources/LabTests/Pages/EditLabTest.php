<?php

namespace App\Filament\TenantAdmin\Resources\LabTests\Pages;

use App\Filament\TenantAdmin\Resources\LabTests\LabTestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLabTest extends EditRecord
{
    protected static string $resource = LabTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
