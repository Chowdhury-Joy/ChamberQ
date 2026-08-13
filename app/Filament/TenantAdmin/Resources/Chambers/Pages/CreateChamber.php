<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChamber extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = ChamberResource::class;
}
