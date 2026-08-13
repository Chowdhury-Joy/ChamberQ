<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use Filament\Resources\Pages\EditRecord;

class EditScheduleSession extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = ScheduleSessionResource::class;
}
