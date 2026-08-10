<?php

namespace App\Services;

use App\Models\User;
use App\Support\DataBackup\BackupCsv;
use App\Support\DataBackup\BackupTableMap;
use App\Support\DataBackup\ImportOptions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DataBackupService
{
    public function tenantBackupFilename(string $tenantId): string
    {
        $stamp = now()->format('Y-m-d_His');

        return "chamberq-tenant-{$tenantId}-{$stamp}.zip";
    }

    public function platformBackupFilename(): string
    {
        $stamp = now()->format('Y-m-d_His');

        return "chamberq-platform-{$stamp}.zip";
    }

    public function streamTenantBackup(string $tenantId): StreamedResponse
    {
        $zipPath = $this->exportTenantToZipPath($tenantId);

        return $this->streamZipFile($zipPath, $this->tenantBackupFilename($tenantId));
    }

    public function streamPlatformBackup(): StreamedResponse
    {
        $zipPath = $this->exportPlatformToZipPath();

        return $this->streamZipFile($zipPath, $this->platformBackupFilename());
    }

    public function exportTenantToZipPath(string $tenantId): string
    {
        $directory = $this->makeTempDirectory('tenant-'.$tenantId);
        $counts = [];

        foreach (BackupTableMap::TENANT_TABLES as $table) {
            $counts[$table] = $this->exportTable(
                $table,
                $directory,
                fn (Builder $query) => $this->applyTenantTableScope($query, $table, $tenantId),
            );
        }

        $manifest = [
            'version' => BackupTableMap::MANIFEST_VERSION,
            'app_version' => (string) config('app.version', '1.0'),
            'exported_at' => now()->toIso8601String(),
            'scope' => ImportOptions::SCOPE_TENANT,
            'tenant_id' => $tenantId,
            'tables' => $counts,
        ];

        file_put_contents(
            $directory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return $this->zipDirectory($directory);
    }

    public function exportPlatformToZipPath(): string
    {
        $directory = $this->makeTempDirectory('platform');
        $counts = [];

        foreach (BackupTableMap::PLATFORM_TABLES as $table) {
            $counts[$table] = $this->exportTable(
                $table,
                $directory,
                fn (Builder $query) => $this->applyPlatformTableScope($query, $table),
            );
        }

        $manifest = [
            'version' => BackupTableMap::MANIFEST_VERSION,
            'app_version' => (string) config('app.version', '1.0'),
            'exported_at' => now()->toIso8601String(),
            'scope' => ImportOptions::SCOPE_PLATFORM,
            'tenant_id' => null,
            'tables' => $counts,
        ];

        file_put_contents(
            $directory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return $this->zipDirectory($directory);
    }

    private function applyTenantTableScope(Builder $query, string $table, string $tenantId): void
    {
        if ($table === 'users') {
            $query->where('tenant_id', $tenantId)
                ->whereIn('role', User::TENANT_PANEL_ROLES);

            return;
        }

        if ($table === 'prescription_items') {
            $query->whereIn('prescription_id', function ($sub) use ($tenantId) {
                $sub->select('id')
                    ->from('prescriptions')
                    ->where('tenant_id', $tenantId);
            });

            return;
        }

        if (BackupTableMap::tableHasTenantColumn($table)) {
            $query->where('tenant_id', $tenantId);
        }
    }

    private function applyPlatformTableScope(Builder $query, string $table): void
    {
        if ($table === 'users') {
            $query->whereNull('tenant_id')
                ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_MARKETER]);

            return;
        }
    }

    /**
     * @param  callable(Builder): void  $scope
     */
    private function exportTable(string $table, string $directory, callable $scope): int
    {
        $columns = BackupCsv::exportableColumns($table);
        $path = $directory.'/'.$table.'.csv';
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Could not write CSV: {$path}");
        }

        BackupCsv::writeHeader($handle, $columns);

        $count = 0;
        $query = DB::table($table);
        $scope($query);

        $query->orderBy(BackupTableMap::primaryKeyColumn($table))
            ->chunk(500, function ($rows) use ($handle, $columns, &$count) {
                foreach ($rows as $row) {
                    BackupCsv::writeRow($handle, $columns, (array) $row);
                    $count++;
                }
            });

        fclose($handle);

        return $count;
    }

    private function makeTempDirectory(string $suffix): string
    {
        $base = storage_path('app/backup-temp/'.Str::uuid().'-'.$suffix);
        File::ensureDirectoryExists($base);

        return $base;
    }

    private function zipDirectory(string $directory): string
    {
        $zipPath = $directory.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup ZIP.');
        }

        foreach (File::allFiles($directory) as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();
        File::deleteDirectory($directory);

        return $zipPath;
    }

    private function streamZipFile(string $zipPath, string $downloadName): StreamedResponse
    {
        return response()->streamDownload(function () use ($zipPath) {
            $stream = fopen($zipPath, 'rb');

            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }

            @unlink($zipPath);
        }, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->setStatusCode(Response::HTTP_OK);
    }
}
