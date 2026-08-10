<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts\Pages;

use App\Filament\TenantAdmin\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;
}
