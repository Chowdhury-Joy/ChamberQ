<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = BlogPostResource::class;
}
