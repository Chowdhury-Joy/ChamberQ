<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages;

use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\ScheduleSessionOverrideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduleSessionOverride extends CreateRecord
{
    protected static string $resource = ScheduleSessionOverrideResource::class;
}
