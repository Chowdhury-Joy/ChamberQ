<?php

namespace App\Filament\SuperAdmin\Resources\DiscountCodes\Pages;

use App\Filament\SuperAdmin\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiscountCodes extends ListRecords
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
