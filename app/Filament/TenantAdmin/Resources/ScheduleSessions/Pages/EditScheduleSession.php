<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduleSession extends EditRecord
{
    protected static string $resource = ScheduleSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
