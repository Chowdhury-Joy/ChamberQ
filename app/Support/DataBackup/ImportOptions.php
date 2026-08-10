<?php

namespace App\Support\DataBackup;

class ImportOptions
{
    public const MODE_REPLACE = 'replace';

    public const MODE_MERGE = 'merge';

    public const SCOPE_TENANT = 'tenant';

    public const SCOPE_PLATFORM = 'platform';

    public function __construct(
        public readonly string $scope,
        public readonly ?string $tenantId,
        public readonly string $mode = self::MODE_REPLACE,
        public readonly bool $dryRun = false,
    ) {}

    public function isTenant(): bool
    {
        return $this->scope === self::SCOPE_TENANT;
    }

    public function isPlatform(): bool
    {
        return $this->scope === self::SCOPE_PLATFORM;
    }
}
