<?php

namespace App\Filament\SuperAdmin\Support;

use App\Models\Tenant;
use App\Services\DataBackupService;
use App\Services\DataImportService;
use App\Support\DataBackup\ImportOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class TenantBackupActions
{
    public static function downloadAction(): Action
    {
        return Action::make('downloadTenantBackup')
            ->label(__('Download chamber backup'))
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (Tenant $record) => app(DataBackupService::class)->streamTenantBackup($record->id));
    }

    public static function restoreAction(): Action
    {
        return Action::make('restoreTenantBackup')
            ->label(__('Restore chamber backup'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->schema([
                FileUpload::make('backup_file')
                    ->label(__('Chamber backup ZIP'))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->required()
                    ->maxSize(512000),
                Select::make('mode')
                    ->label(__('Restore mode'))
                    ->options([
                        ImportOptions::MODE_REPLACE => __('Replace — wipe this chamber, then restore'),
                        ImportOptions::MODE_MERGE => __('Merge — update matching rows only'),
                    ])
                    ->default(ImportOptions::MODE_REPLACE)
                    ->required()
                    ->native(false),
                Checkbox::make('dry_run')
                    ->label(__('Dry run only')),
                TextInput::make('confirmation')
                    ->label(__('Type the chamber ID to confirm'))
                    ->required()
                    ->visible(fn (callable $get): bool => $get('mode') === ImportOptions::MODE_REPLACE && ! $get('dry_run')),
            ])
            ->action(function (Tenant $record, array $data): void {
                if (
                    ($data['mode'] ?? ImportOptions::MODE_REPLACE) === ImportOptions::MODE_REPLACE
                    && ! ($data['dry_run'] ?? false)
                    && ($data['confirmation'] ?? '') !== $record->id
                ) {
                    Notification::make()
                        ->title(__('Confirmation did not match the chamber ID'))
                        ->danger()
                        ->send();

                    return;
                }

                $uploaded = $data['backup_file'] ?? null;
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
                            tenantId: $record->id,
                            mode: $data['mode'] ?? ImportOptions::MODE_REPLACE,
                            dryRun: (bool) ($data['dry_run'] ?? false),
                        ),
                    );

                    Notification::make()
                        ->title($result->dryRun ? __('Dry run complete') : __('Chamber backup restored'))
                        ->body(__('Processed :count rows for :tenant.', [
                            'count' => $result->totalRows(),
                            'tenant' => $record->id,
                        ]))
                        ->success()
                        ->send();
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title(__('Restore failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
