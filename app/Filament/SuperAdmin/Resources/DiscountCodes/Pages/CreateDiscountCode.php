<?php

namespace App\Filament\SuperAdmin\Resources\DiscountCodes\Pages;

use App\Filament\SuperAdmin\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscountCode extends CreateRecord
{
    protected static string $resource = DiscountCodeResource::class;
}
