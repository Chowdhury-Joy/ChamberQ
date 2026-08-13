<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = UserResource::class;
}
