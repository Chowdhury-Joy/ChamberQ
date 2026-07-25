<?php

namespace App\Filament\TenantAdmin\Resources\WebPages\Pages;

use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebPage extends CreateRecord
{
    protected static string $resource = WebPageResource::class;
}
