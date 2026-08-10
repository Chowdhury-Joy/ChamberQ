<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts\Pages;

use App\Filament\TenantAdmin\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;
}
