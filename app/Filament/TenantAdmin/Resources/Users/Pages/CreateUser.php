<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
