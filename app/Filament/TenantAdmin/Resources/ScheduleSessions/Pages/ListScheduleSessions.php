<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleSessions extends ListRecords
{
    protected static string $resource = ScheduleSessionResource::class;

    public function getSubheading(): ?string
    {
        if (! (tenant()?->hasStations() ?? false)) {
            return null;
        }

        return __('Visit and intervention hours only. Lab and report are rooms — tick them on Branding, not here.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
