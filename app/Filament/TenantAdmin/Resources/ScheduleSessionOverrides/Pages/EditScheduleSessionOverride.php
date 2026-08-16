<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\ScheduleSessionOverrideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduleSessionOverride extends EditRecord
{
    protected static string $resource = ScheduleSessionOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
