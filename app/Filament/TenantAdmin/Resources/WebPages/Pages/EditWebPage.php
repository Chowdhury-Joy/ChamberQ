<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\WebPages\Concerns\ConfiguresPageEditorChrome;
use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Resources\Pages\EditRecord;

class EditWebPage extends EditRecord
{
    use ConfiguresPageEditorChrome;
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = WebPageResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function getHeaderActions(): array
    {
        return [
            $this->saveChangesAction(
                $this->getSaveFormAction()
                    ->submit(null)
                    ->action('save')
            ),
        ];
    }
}
