<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\User;
use App\Services\DataBackupService;
use App\Services\DataImportService;
use App\Support\DataBackup\ImportOptions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataBackup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationLabel = 'Data backup';

    protected static ?string $title = 'Data backup';

    protected static ?int $navigationSort = 99;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'data-backup';

    protected string $view = 'filament.tenant-admin.pages.data-backup';

    /** @var array<string, mixed>|null */
    public ?array $importData = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canManageUsers() ?? false;
    }

    public function mount(): void
    {
        $this->importForm->fill([
            'mode' => ImportOptions::MODE_REPLACE,
            'dry_run' => false,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'importForm',
        ];
    }

    public function importForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('importData')
            ->components([
                FileUpload::make('backup_file')
                    ->label(__('Backup ZIP'))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->required()
                    ->maxSize(512000),
                Select::make('mode')
                    ->label(__('Restore mode'))
                    ->options([
                        ImportOptions::MODE_REPLACE => __('Replace — wipe this chamber’s data, then restore from ZIP'),
                        ImportOptions::MODE_MERGE => __('Merge — update matching rows, keep anything not in the ZIP'),
                    ])
                    ->required()
                    ->native(false),
                Checkbox::make('dry_run')
                    ->label(__('Dry run — check the ZIP without writing anything')),
                TextInput::make('confirmation')
                    ->label(__('Type the chamber ID to confirm'))
                    ->placeholder(tenant('id'))
                    ->required(fn (): bool => ! ($this->importData['dry_run'] ?? false))
                    ->visible(fn (): bool => ($this->importData['mode'] ?? ImportOptions::MODE_REPLACE) === ImportOptions::MODE_REPLACE
                        && ! ($this->importData['dry_run'] ?? false))
                    ->rule(function (): \Closure {
                        return function (string $attribute, mixed $value, \Closure $fail): void {
                            if ((string) $value !== (string) tenant('id')) {
                                $fail(__('The confirmation must match this chamber’s ID exactly.'));
                            }
                        };
                    }),
            ]);
    }

    public function downloadBackup(): StreamedResponse
    {
        $tenantId = (string) tenant('id');

        return app(DataBackupService::class)->streamTenantBackup($tenantId);
    }

    public function restoreBackup(): void
    {
        $data = $this->importForm->getState();
        $uploaded = $data['backup_file'] ?? null;

        if (blank($uploaded)) {
            Notification::make()
                ->title(__('Choose a backup ZIP first'))
                ->danger()
                ->send();

            return;
        }

        $path = is_array($uploaded) ? reset($uploaded) : $uploaded;

        if (! is_string($path) || ! is_readable($path)) {
            Notification::make()
                ->title(__('Could not read the uploaded ZIP'))
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(DataImportService::class)->importFromZip(
                $path,
                new ImportOptions(
                    scope: ImportOptions::SCOPE_TENANT,
                    tenantId: (string) tenant('id'),
                    mode: $data['mode'] ?? ImportOptions::MODE_REPLACE,
                    dryRun: (bool) ($data['dry_run'] ?? false),
                ),
            );

            $message = $result->dryRun
                ? __('Dry run OK — :count rows across :tables tables would be restored.', [
                    'count' => $result->totalRows(),
                    'tables' => count(array_filter($result->tableCounts)),
                ])
                : __('Restored :count rows. Staff must use Forgot password to sign in again.', [
                    'count' => $result->totalRows(),
                ]);

            Notification::make()
                ->title($result->dryRun ? __('Dry run complete') : __('Backup restored'))
                ->body($message)
                ->success()
                ->send();

            $this->importForm->fill([
                'mode' => ImportOptions::MODE_REPLACE,
                'dry_run' => false,
            ]);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title(__('Restore failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
