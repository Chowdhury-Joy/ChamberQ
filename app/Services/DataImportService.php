<?php

namespace App\Services;

use App\Models\User;
use App\Support\DataBackup\BackupCsv;
use App\Support\DataBackup\BackupTableMap;
use App\Support\DataBackup\ImportOptions;
use App\Support\DataBackup\ImportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

class DataImportService
{
    /**
     * @return array{manifest: array<string, mixed>, directory: string}
     */
    public function extractZip(string $zipPath): array
    {
        if (! is_readable($zipPath)) {
            throw new \InvalidArgumentException('Backup ZIP is not readable.');
        }

        $directory = storage_path('app/backup-import/'.Str::uuid());
        File::ensureDirectoryExists($directory);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \InvalidArgumentException('Could not open backup ZIP.');
        }

        $zip->extractTo($directory);
        $zip->close();

        $manifestPath = $directory.'/manifest.json';

        if (! is_readable($manifestPath)) {
            File::deleteDirectory($directory);

            throw new \InvalidArgumentException('Backup ZIP is missing manifest.json.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            File::deleteDirectory($directory);

            throw new \InvalidArgumentException('Backup manifest.json is invalid.');
        }

        if (($manifest['version'] ?? null) !== BackupTableMap::MANIFEST_VERSION) {
            File::deleteDirectory($directory);

            throw new \InvalidArgumentException('Unsupported backup manifest version.');
        }

        return ['manifest' => $manifest, 'directory' => $directory];
    }

    public function importFromZip(string $zipPath, ImportOptions $options): ImportResult
    {
        ['manifest' => $manifest, 'directory' => $extracted] = $this->extractZip($zipPath);

        try {
            $this->validateManifestAgainstOptions($manifest, $options);

            $tables = $options->isTenant()
                ? BackupTableMap::TENANT_TABLES
                : BackupTableMap::PLATFORM_TABLES;

            $counts = [];

            if (! $options->dryRun && $options->mode === ImportOptions::MODE_REPLACE) {
                $this->wipeScope($options);
            }

            foreach ($tables as $table) {
                $csvPath = $extracted.'/'.$table.'.csv';

                if (! is_readable($csvPath)) {
                    $counts[$table] = 0;

                    continue;
                }

                $parsed = BackupCsv::readFile($csvPath);
                $rows = $parsed['rows'];

                if ($options->dryRun) {
                    $counts[$table] = count($rows);

                    continue;
                }

                $counts[$table] = $this->importTableRows($table, $rows, $options);
            }

            return new ImportResult(
                dryRun: $options->dryRun,
                tableCounts: $counts,
                manifestTenantId: $manifest['tenant_id'] ?? null,
            );
        } finally {
            File::deleteDirectory($extracted);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function validateManifestAgainstOptions(array $manifest, ImportOptions $options): void
    {
        $scope = $manifest['scope'] ?? null;

        if ($options->isTenant() && $scope !== ImportOptions::SCOPE_TENANT) {
            throw new \InvalidArgumentException('This ZIP is not a chamber backup.');
        }

        if ($options->isPlatform() && $scope !== ImportOptions::SCOPE_PLATFORM) {
            throw new \InvalidArgumentException('This ZIP is not a platform backup.');
        }

        if ($options->isTenant() && blank($options->tenantId)) {
            throw new \InvalidArgumentException('A target chamber is required for restore.');
        }
    }

    private function wipeScope(ImportOptions $options): void
    {
        $tables = $options->isTenant()
            ? BackupTableMap::tenantTablesInDeleteOrder()
            : BackupTableMap::platformTablesInDeleteOrder();

        DB::transaction(function () use ($tables, $options) {
            foreach ($tables as $table) {
                $query = DB::table($table);

                if ($options->isTenant()) {
                    $this->applyTenantWipeScope($query, $table, (string) $options->tenantId);
                } else {
                    $this->applyPlatformWipeScope($query, $table);
                }

                $query->delete();
            }
        });
    }

    private function applyTenantWipeScope(Builder $query, string $table, string $tenantId): void
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

    private function applyPlatformWipeScope(Builder $query, string $table): void
    {
        if ($table === 'users') {
            $query->whereNull('tenant_id')
                ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_MARKETER]);

            return;
        }
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     */
    private function importTableRows(string $table, array $rows, ImportOptions $options): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = BackupCsv::exportableColumns($table);
        $primaryKey = BackupTableMap::primaryKeyColumn($table);
        $imported = 0;
        $upsertColumns = $table === 'users'
            ? array_values(array_unique(array_merge($columns, ['password'])))
            : $columns;

        foreach (array_chunk($rows, 200) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                $record = BackupCsv::deserializeRow($row, $table);
                $record = $this->normalizeImportedRow($table, $record, $options);

                if (! array_key_exists($primaryKey, $record) || blank($record[$primaryKey])) {
                    continue;
                }

                $payload[] = array_intersect_key($record, array_flip($upsertColumns));
            }

            if ($payload === []) {
                continue;
            }

            DB::table($table)->upsert(
                $payload,
                [$primaryKey],
                array_values(array_diff($upsertColumns, [$primaryKey])),
            );

            $imported += count($payload);
        }

        return $imported;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeImportedRow(string $table, array $row, ImportOptions $options): array
    {
        if ($options->isTenant() && BackupTableMap::tableHasTenantColumn($table)) {
            $row['tenant_id'] = $options->tenantId;
        }

        if ($table === 'users') {
            $row['password'] = Hash::make(Str::random(64));
            unset($row['remember_token']);
        }

        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        return $row;
    }
}
