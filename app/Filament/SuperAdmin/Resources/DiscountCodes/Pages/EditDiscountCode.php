<?php

namespace App\Filament\SuperAdmin\Resources\DiscountCodes\Pages;

use App\Filament\SuperAdmin\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
