<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = UserResource::class;
}
