<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleSessions extends ListRecords
{
    protected static string $resource = ScheduleSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
