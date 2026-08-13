<?php

namespace App\Filament\TenantAdmin\Resources\LabTests\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\LabTests\LabTestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLabTest extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = LabTestResource::class;
}
