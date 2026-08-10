<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts\Pages;

use App\Filament\TenantAdmin\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\ListRecords;

class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;
}
