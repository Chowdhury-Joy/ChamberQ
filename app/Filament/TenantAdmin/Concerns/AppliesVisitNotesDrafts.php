<?php

namespace App\Filament\TenantAdmin\Concerns;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use Livewire\Attributes\On;

trait AppliesVisitNotesDrafts
{
    #[On('visit-notes-draft')]
    public function applyVisitNotesDraft(array $draft): void
    {
        if (! tenant()?->hasFeature('voice_transcription')) {
            return;
        }

        $action = $this->getMountedAction();

        if (! $action) {
            return;
        }

        $schema = $this->getMountedActionSchema(mountedAction: $action);
        $current = $schema->getStateSnapshot();
        $merged = VisitNotesFormSchema::mergeDraftIntoState($current, $draft);
        $schema->fill($merged);
    }

    #[On('copy-last-prescription')]
    public function copyLastPrescription(array $items): void
    {
        $action = $this->getMountedAction();

        if (! $action) {
            return;
        }

        $schema = $this->getMountedActionSchema(mountedAction: $action);
        $current = $schema->getStateSnapshot();
        $current['prescription_items'] = $items;
        $schema->fill($current);
    }
}
