<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Schemas;

use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
