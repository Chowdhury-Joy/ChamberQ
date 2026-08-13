<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduleSession extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = ScheduleSessionResource::class;
}
