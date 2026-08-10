<?php

namespace App\Console\Commands;

use App\Services\DataBackupService;
use Illuminate\Console\Command;

class DataBackupExportCommand extends Command
{
    protected $signature = 'data:backup-export
        {tenant? : Chamber ID for a tenant backup}
        {--platform : Export platform tables instead of a single chamber}';

    protected $description = 'Export disaster-recovery CSV backup as a ZIP file';

    public function handle(DataBackupService $backup): int
    {
        if ($this->option('platform')) {
            $zipPath = $backup->exportPlatformToZipPath();
            $this->info("Platform backup written to: {$zipPath}");

            return self::SUCCESS;
        }

        $tenantId = $this->argument('tenant');

        if (blank($tenantId)) {
            $this->error('Provide a chamber ID or pass --platform.');

            return self::FAILURE;
        }

        $zipPath = $backup->exportTenantToZipPath((string) $tenantId);
        $this->info("Chamber backup written to: {$zipPath}");

        return self::SUCCESS;
    }
}
