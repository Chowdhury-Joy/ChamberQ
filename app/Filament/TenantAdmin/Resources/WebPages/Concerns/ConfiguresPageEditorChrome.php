<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Concerns;

use App\Models\WebPage;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

trait ConfiguresPageEditorChrome
{
    public function areFormActionsSticky(): bool
    {
        return false;
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::End;
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return $this->isHomepageRecord() ? Width::Full : Width::FiveExtraLarge;
    }

    protected function isHomepageRecord(): bool
    {
        $record = $this->getRecord();

        return $record instanceof WebPage && $record->slug === '/';
    }

    protected function saveChangesAction(Action $action): Action
    {
        return $action->label('Save changes');
    }
}
