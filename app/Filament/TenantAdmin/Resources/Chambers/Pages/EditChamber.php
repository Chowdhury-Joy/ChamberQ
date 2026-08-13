<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use Filament\Resources\Pages\EditRecord;

class EditChamber extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = ChamberResource::class;
}
