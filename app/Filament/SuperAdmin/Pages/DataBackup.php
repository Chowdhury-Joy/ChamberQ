<?php

namespace App\Filament\SuperAdmin\Pages;

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

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Data backup';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Platform data backup';

    protected static ?string $slug = 'data-backup';

    protected string $view = 'filament.super-admin.pages.data-backup';

    /** @var array<string, mixed>|null */
    public ?array $importData = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->role === User::ROLE_SUPER_ADMIN && $user->tenant_id === null;
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
                    ->label(__('Platform backup ZIP'))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->required()
                    ->maxSize(512000),
                Select::make('mode')
                    ->label(__('Restore mode'))
                    ->options([
                        ImportOptions::MODE_REPLACE => __('Replace — wipe platform tables, then restore'),
                        ImportOptions::MODE_MERGE => __('Merge — update matching rows only'),
                    ])
                    ->required()
                    ->native(false),
                Checkbox::make('dry_run')
                    ->label(__('Dry run — check the ZIP without writing anything')),
                TextInput::make('confirmation')
                    ->label(__('Type REPLACE to confirm platform restore'))
                    ->required(fn (): bool => ! ($this->importData['dry_run'] ?? false))
                    ->visible(fn (): bool => ($this->importData['mode'] ?? ImportOptions::MODE_REPLACE) === ImportOptions::MODE_REPLACE
                        && ! ($this->importData['dry_run'] ?? false))
                    ->rule('in:REPLACE'),
            ]);
    }

    public function downloadPlatformBackup(): StreamedResponse
    {
        return app(DataBackupService::class)->streamPlatformBackup();
    }

    public function restorePlatformBackup(): void
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
                    scope: ImportOptions::SCOPE_PLATFORM,
                    tenantId: null,
                    mode: $data['mode'] ?? ImportOptions::MODE_REPLACE,
                    dryRun: (bool) ($data['dry_run'] ?? false),
                ),
            );

            Notification::make()
                ->title($result->dryRun ? __('Dry run complete') : __('Platform backup restored'))
                ->body(__('Processed :count rows.', ['count' => $result->totalRows()]))
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
