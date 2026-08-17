<x-filament-panels::page>
    <style>
        .backup-page { display: flex; flex-direction: column; gap: 1.75rem; }
        .backup-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: var(--color-white);
            overflow: hidden;
        }
        .dark .backup-card {
            border-color: var(--gray-700);
            background: var(--gray-900);
        }
        .backup-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        .dark .backup-card-header {
            border-color: var(--gray-700);
            background: var(--gray-800);
        }
        .backup-card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .backup-card-title { color: var(--color-white); }
        .backup-card-body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .backup-restore-submit { margin-top: 1.25rem; }
        .backup-btn-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
        .backup-hint {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: var(--gray-600);
            line-height: 1.5;
        }
        .dark .backup-hint { color: var(--gray-400); }
        .backup-note {
            margin: 0;
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            background: var(--warning-50);
            color: var(--warning-800);
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .dark .backup-note {
            background: color-mix(in srgb, var(--warning-500) 15%, transparent);
            color: var(--warning-200);
        }
        .backup-danger-note {
            margin: 0;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--danger-200);
            background: var(--danger-50);
            color: var(--danger-800);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.5;
        }
        .dark .backup-danger-note {
            border-color: color-mix(in srgb, var(--danger-500) 40%, transparent);
            background: color-mix(in srgb, var(--danger-500) 15%, transparent);
            color: var(--danger-200);
        }
    </style>

    <div class="backup-page">
        <p class="backup-note">
            {{ __('Platform backup covers tenants, domains, marketers, discount codes, commissions, billing payments, and booking-window settings. Chamber clinical data is restored per tenant from the Tenants list. Passwords are never included — use Forgot password after restore.') }}
        </p>

        <section class="backup-card">
            <div class="backup-card-header">
                <h2 class="backup-card-title">{{ __('Download platform backup') }}</h2>
                <p class="backup-hint">{{ __('ZIP of all central platform tables.') }}</p>
            </div>
            <div class="backup-card-body">
                <div class="backup-btn-row">
                    <x-filament::button
                        wire:click="downloadPlatformBackup"
                        wire:target="downloadPlatformBackup"
                        wire:loading.attr="disabled"
                        icon="heroicon-o-arrow-down-tray"
                        color="primary"
                    >
                        <span wire:loading.remove wire:target="downloadPlatformBackup">{{ __('Download platform backup') }}</span>
                        <span wire:loading wire:target="downloadPlatformBackup">{{ __('Preparing download…') }}</span>
                    </x-filament::button>
                </div>
            </div>
        </section>

        <section class="backup-card">
            <div class="backup-card-header">
                <h2 class="backup-card-title">{{ __('Restore platform backup') }}</h2>
                <p class="backup-hint">{{ __('Dangerous — only use after a wipe or cyber attack. Per-chamber data is on each tenant’s backup action.') }}</p>
            </div>
            <div class="backup-card-body">
                <form
                    wire:submit="restorePlatformBackup"
                    @if (! $this->isDryRunRestore())
                        wire:confirm="{{ __('This wipes every central platform table and replaces them from the ZIP. Tenants, marketers, commissions and billing payments will be overwritten. Continue?') }}"
                    @endif
                >
                    {{ $this->importForm }}

                    @unless ($this->isDryRunRestore())
                        <p class="backup-danger-note">
                            {{ __('Dry run is off — submitting will write to the live platform database.') }}
                        </p>
                    @endunless

                    <div class="backup-restore-submit">
                        {{-- Keyed on the dry-run state so Livewire swaps the element instead of
                             morphing it: a morphed button keeps its painted background, so the
                             danger red never appeared and the destructive submit still looked
                             like the safe one. --}}
                        <x-filament::button
                            type="submit"
                            wire:key="restore-submit-{{ $this->isDryRunRestore() ? 'dry' : 'live' }}"
                            wire:target="restorePlatformBackup"
                            wire:loading.attr="disabled"
                            icon="heroicon-o-arrow-up-tray"
                            :color="$this->isDryRunRestore() ? 'primary' : 'danger'"
                        >
                            <span wire:loading.remove wire:target="restorePlatformBackup">
                                {{ $this->isDryRunRestore()
                                    ? __('Check ZIP without writing')
                                    : __('Upload and restore platform data') }}
                            </span>
                            <span wire:loading wire:target="restorePlatformBackup">{{ __('Working…') }}</span>
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</x-filament-panels::page>
