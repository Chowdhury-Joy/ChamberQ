<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyItems\Pages;

use App\Filament\TenantAdmin\Resources\PharmacyItems\PharmacyItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePharmacyItem extends CreateRecord
{
    protected static string $resource = PharmacyItemResource::class;
}
