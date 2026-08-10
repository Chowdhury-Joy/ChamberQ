<?php

namespace App\Support\DataBackup;

class ImportResult
{
    /** @param  array<string, int>  $tableCounts */
    public function __construct(
        public readonly bool $dryRun,
        public readonly array $tableCounts,
        public readonly ?string $manifestTenantId = null,
    ) {}

    public function totalRows(): int
    {
        return array_sum($this->tableCounts);
    }
}
