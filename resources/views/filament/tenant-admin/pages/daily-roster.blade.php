<x-filament-panels::page wire:poll.15s>
    @include('filament.tenant-admin.components.sitting-prompts', [
        'prompts' => $this->sittingPrompts,
        'canOperate' => auth()->user()?->canManageQueue() ?? false,
        'liveQueueUrl' => \App\Filament\TenantAdmin\Pages\LiveQueueControl::getUrl(),
        'selectedSessionId' => null,
        'context' => 'roster',
    ])

    <div style="margin-top: {{ $this->sittingPrompts->isNotEmpty() ? '1rem' : '0' }}">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
