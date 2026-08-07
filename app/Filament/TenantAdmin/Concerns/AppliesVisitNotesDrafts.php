<?php

namespace App\Filament\TenantAdmin\Concerns;

use Livewire\Attributes\On;

/**
 * Prefills the mounted visit-notes modal from a client-side event.
 *
 * The voice → field auto-fill listener was removed with the deferred
 * transcription feature; see docs/deferred/voice-transcription/README.md.
 */
trait AppliesVisitNotesDrafts
{
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
