<?php

namespace App\Filament\TenantAdmin\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;

/**
 * Create pages: Create (or Save changes) is the header main CTA; Cancel
 * stays in the form footer.
 *
 * @mixin CreateRecord
 */
trait HasPrimaryCreate
{
    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->primaryCreateAction(),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
        ];
    }

    protected function primaryCreateAction(): Action
    {
        return $this->getCreateFormAction()
            ->submit(null)
            ->action('create');
    }
}
