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

            // A dry run only parses, so it never opens a transaction.
            if ($options->dryRun) {
                $counts = [];

                foreach ($tables as $table) {
                    $csvPath = $extracted.'/'.$table.'.csv';
                    $counts[$table] = is_readable($csvPath)
                        ? count(BackupCsv::readFile($csvPath)['rows'])
                        : 0;
                }

                return new ImportResult(
                    dryRun: true,
                    tableCounts: $counts,
                    manifestTenantId: $manifest['tenant_id'] ?? null,
                );
            }

            // Wipe AND import in ONE transaction. Previously the wipe ran in a
            // transaction of its own and committed, then the import ran outside
            // it — so any failure mid-import (a foreign key, a bad row) left the
            // chamber emptied with nothing to roll it back. A restore either
            // lands completely or changes nothing.
            $counts = DB::transaction(function () use ($tables, $extracted, $options): array {
                $counts = [];

                if ($options->mode === ImportOptions::MODE_REPLACE) {
                    $this->wipeScope($options);
                }

                foreach ($tables as $table) {
                    $csvPath = $extracted.'/'.$table.'.csv';

                    if (! is_readable($csvPath)) {
                        $counts[$table] = 0;

                        continue;
                    }

                    $parsed = BackupCsv::readFile($csvPath);

                    $counts[$table] = $this->importTableRows($table, $parsed['rows'], $options);
                }

                return $counts;
            });

            return new ImportResult(
                dryRun: false,
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

    /**
     * Caller wraps this in the same transaction as the import — see
     * importFromZip(). It deliberately opens no transaction of its own, because
     * a committed wipe with a failed import is the one outcome this whole
     * feature must never produce.
     */
    private function wipeScope(ImportOptions $options): void
    {
        $tables = $options->isTenant()
            ? BackupTableMap::tenantTablesInDeleteOrder()
            : BackupTableMap::platformTablesInDeleteOrder();

        if ($options->isTenant()) {
            // live_sessions.current_booking_id points at a booking row and has
            // no ON DELETE rule. Delete order clears live_sessions first, but a
            // session pointing at a booking outside this tenant's set would
            // still block the delete, so drop the pointer before touching rows.
            DB::table('live_sessions')
                ->where('tenant_id', (string) $options->tenantId)
                ->update(['current_booking_id' => null]);
        }

        foreach ($tables as $table) {
            $query = DB::table($table);

            if ($options->isTenant()) {
                $this->applyTenantWipeScope($query, $table, (string) $options->tenantId);
            } else {
                $this->applyPlatformWipeScope($query, $table);
            }

            $query->delete();
        }
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

            $this->assertPayloadBelongsToScope($table, $payload, $primaryKey, $options);

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
     * Refuse a restore that would write over rows this scope does not own.
     *
     * The upsert below matches on the bare primary key, and every chamber lives
     * in one shared database, so without this a chamber admin could hand in a
     * ZIP whose `users.csv` reuses another chamber's — or the central Super
     * Admin's — row id and have those rows rewritten into their own chamber.
     * `users.id` is a plain auto-increment integer, so nothing has to be
     * guessed. `BelongsToTenant` cannot help here: these are Query Builder
     * writes and never touch Eloquent's guard.
     *
     * Throwing rather than skipping is deliberate. A backup containing rows
     * that belong to somebody else is either corrupt or hostile; either way the
     * honest outcome is a failed restore the admin can see, not a half-applied
     * one they cannot. The whole import runs in one transaction, so this rolls
     * everything back.
     *
     * @param  list<array<string, mixed>>  $payload
     */
    private function assertPayloadBelongsToScope(
        string $table,
        array $payload,
        string $primaryKey,
        ImportOptions $options,
    ): void {
        $ids = array_values(array_filter(
            array_column($payload, $primaryKey),
            static fn ($id): bool => $id !== null && $id !== '',
        ));

        if ($ids === []) {
            return;
        }

        if ($table === 'prescription_items') {
            $this->assertPrescriptionItemsBelongToScope($payload, $ids, $options);

            return;
        }

        if ($options->isPlatform()) {
            // Platform restores are the owner's own central data. The one row
            // that must not move is a tenant staff account being pulled into
            // the central (tenant_id null) space.
            if ($table === 'users') {
                $foreign = DB::table('users')
                    ->whereIn('id', $ids)
                    ->whereNotNull('tenant_id')
                    ->count();

                if ($foreign > 0) {
                    throw new \InvalidArgumentException(
                        'This backup reuses the id of a chamber staff account. Restore refused.'
                    );
                }
            }

            return;
        }

        if (! BackupTableMap::tableHasTenantColumn($table)) {
            return;
        }

        $target = (string) $options->tenantId;

        $foreign = DB::table($table)
            ->whereIn($primaryKey, $ids)
            ->where(function (Builder $query) use ($target): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '!=', $target);
            })
            ->count();

        if ($foreign > 0) {
            throw new \InvalidArgumentException(sprintf(
                'This backup reuses %d row id(s) in "%s" that belong to another chamber '
                .'or to the platform. Restore refused.',
                $foreign,
                $table,
            ));
        }
    }

    /**
     * `prescription_items` carries no `tenant_id` — it is owned through its
     * parent prescription — so both the row it would overwrite and the
     * prescription it would attach to have to be checked separately.
     *
     * @param  list<array<string, mixed>>  $payload
     * @param  list<mixed>  $ids
     */
    private function assertPrescriptionItemsBelongToScope(
        array $payload,
        array $ids,
        ImportOptions $options,
    ): void {
        if (! $options->isTenant()) {
            return;
        }

        $target = (string) $options->tenantId;

        // Rows already on disk under one of these ids, owned by someone else.
        $foreignExisting = DB::table('prescription_items')
            ->whereIn('prescription_items.id', $ids)
            ->whereNotIn('prescription_items.prescription_id', function ($sub) use ($target): void {
                $sub->select('id')->from('prescriptions')->where('tenant_id', $target);
            })
            ->count();

        if ($foreignExisting > 0) {
            throw new \InvalidArgumentException(
                'This backup reuses prescription line ids belonging to another chamber. Restore refused.'
            );
        }

        // Incoming rows must attach to a prescription this chamber owns.
        $parentIds = array_values(array_unique(array_filter(
            array_column($payload, 'prescription_id'),
            static fn ($id): bool => $id !== null && $id !== '',
        )));

        if ($parentIds === []) {
            return;
        }

        $ownedParents = DB::table('prescriptions')
            ->whereIn('id', $parentIds)
            ->where('tenant_id', $target)
            ->count();

        if ($ownedParents !== count($parentIds)) {
            throw new \InvalidArgumentException(
                'This backup contains prescription lines pointing at a prescription this chamber '
                .'does not own. Restore refused.'
            );
        }
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
