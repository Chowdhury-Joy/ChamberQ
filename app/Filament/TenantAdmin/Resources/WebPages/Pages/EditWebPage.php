<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Resources\WebPages\Concerns\ConfiguresPageEditorChrome;
use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebPage extends EditRecord
{
    use ConfiguresPageEditorChrome;

    protected static string $resource = WebPageResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->saveChangesAction($this->getSaveFormAction()),
        ];
    }
}
