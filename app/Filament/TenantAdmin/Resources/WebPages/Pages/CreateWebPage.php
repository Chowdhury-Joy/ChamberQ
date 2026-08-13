<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Resources\WebPages\Concerns\ConfiguresPageEditorChrome;
use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebPage extends CreateRecord
{
    use ConfiguresPageEditorChrome;

    protected static string $resource = WebPageResource::class;

    protected static bool $canCreateAnother = false;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function getFormActions(): array
    {
        return [
            $this->saveChangesAction($this->getCreateFormAction()),
        ];
    }
}
