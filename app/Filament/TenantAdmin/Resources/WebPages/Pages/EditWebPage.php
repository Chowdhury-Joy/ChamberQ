<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebPage extends EditRecord
{
    protected static string $resource = WebPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
