<?php

namespace App\Filament\TenantAdmin\Resources\LabTests\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\LabTests\LabTestResource;
use Filament\Resources\Pages\EditRecord;

class EditLabTest extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = LabTestResource::class;
}
