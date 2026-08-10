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
    </style>

    <div class="backup-page">
        <p class="backup-note">
            {{ __('Download a ZIP of this chamber’s patients, bookings, visit notes, schedules, and staff accounts. Voice notes and prescription photos are not inside the ZIP — only their file paths are saved. Keep a separate copy of server files for full recovery.') }}
        </p>

        <section class="backup-card">
            <div class="backup-card-header">
                <h2 class="backup-card-title">{{ __('Download backup') }}</h2>
                <p class="backup-hint">{{ __('Save the ZIP somewhere safe (Google Drive, an external drive). Do this regularly.') }}</p>
            </div>
            <div class="backup-card-body">
                <x-filament::button
                    wire:click="downloadBackup"
                    icon="heroicon-o-arrow-down-tray"
                    color="primary"
                >
                    {{ __('Download chamber backup') }}
                </x-filament::button>
            </div>
        </section>

        <section class="backup-card">
            <div class="backup-card-header">
                <h2 class="backup-card-title">{{ __('Restore from backup') }}</h2>
                <p class="backup-hint">{{ __('Upload a ZIP you exported from this chamber. Replace mode wipes existing data first. After restore, staff must use Forgot password — passwords are never stored in backups.') }}</p>
            </div>
            <div class="backup-card-body">
                <form wire:submit="restoreBackup">
                    {{ $this->importForm }}

                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" color="danger">
                        {{ __('Upload and restore') }}
                    </x-filament::button>
                </form>
            </div>
        </section>
    </div>
</x-filament-panels::page>
