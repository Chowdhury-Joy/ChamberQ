<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\ScheduleSessionOverrideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleSessionOverrides extends ListRecords
{
    protected static string $resource = ScheduleSessionOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
