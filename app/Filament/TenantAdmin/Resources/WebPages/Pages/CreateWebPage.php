<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\WebPages\Concerns\ConfiguresPageEditorChrome;
use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateWebPage extends CreateRecord
{
    use ConfiguresPageEditorChrome;
    use HasPrimaryCreate;

    protected static string $resource = WebPageResource::class;

    protected static bool $canCreateAnother = false;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function primaryCreateAction(): Action
    {
        return $this->saveChangesAction(
            $this->getCreateFormAction()
                ->submit(null)
                ->action('create')
        );
    }
}
