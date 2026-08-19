<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyItems\Pages;

use App\Filament\TenantAdmin\Resources\PharmacyItems\PharmacyItemResource;
use Filament\Resources\Pages\EditRecord;

class EditPharmacyItem extends EditRecord
{
    protected static string $resource = PharmacyItemResource::class;
}
