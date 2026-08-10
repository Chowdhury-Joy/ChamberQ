<?php

namespace App\Console\Commands;

use App\Services\DataImportService;
use App\Support\DataBackup\ImportOptions;
use Illuminate\Console\Command;

class DataBackupImportCommand extends Command
{
    protected $signature = 'data:backup-import
        {zip : Path to the backup ZIP}
        {--tenant= : Target chamber ID for a tenant backup}
        {--platform : Import a platform backup ZIP}
        {--mode=replace : replace or merge}
        {--dry-run : Parse the ZIP without writing}';

    protected $description = 'Restore disaster-recovery data from a CSV backup ZIP';

    public function handle(DataImportService $import): int
    {
        $zipPath = $this->argument('zip');
        $mode = $this->option('mode');

        if (! in_array($mode, [ImportOptions::MODE_REPLACE, ImportOptions::MODE_MERGE], true)) {
            $this->error('Mode must be replace or merge.');

            return self::FAILURE;
        }

        $scope = $this->option('platform')
            ? ImportOptions::SCOPE_PLATFORM
            : ImportOptions::SCOPE_TENANT;

        $tenantId = $this->option('tenant');

        if ($scope === ImportOptions::SCOPE_TENANT && blank($tenantId)) {
            $this->error('Tenant restore requires --tenant=chamber-id');

            return self::FAILURE;
        }

        if ($scope === ImportOptions::SCOPE_PLATFORM && $mode === ImportOptions::MODE_REPLACE && ! $this->option('dry-run')) {
            if (! $this->confirm('Replace mode will wipe platform tables before import. Continue?')) {
                return self::FAILURE;
            }
        }

        try {
            $result = $import->importFromZip(
                $zipPath,
                new ImportOptions(
                    scope: $scope,
                    tenantId: filled($tenantId) ? (string) $tenantId : null,
                    mode: $mode,
                    dryRun: (bool) $this->option('dry-run'),
                ),
            );

            foreach ($result->tableCounts as $table => $count) {
                if ($count > 0) {
                    $this->line(sprintf('  %s: %d', $table, $count));
                }
            }

            $this->info(($result->dryRun ? 'Dry run' : 'Import').' complete — '.$result->totalRows().' rows.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
