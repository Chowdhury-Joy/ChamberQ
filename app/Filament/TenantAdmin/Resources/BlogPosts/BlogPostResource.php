<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts;

use App\Filament\TenantAdmin\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\TenantAdmin\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\TenantAdmin\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\TenantAdmin\Resources\BlogPosts\Schemas\BlogPostForm;
use App\Filament\TenantAdmin\Resources\BlogPosts\Tables\BlogPostsTable;
use App\Filament\TenantAdmin\Resources\Concerns\ClinicWebsiteResource;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    use ClinicWebsiteResource;

    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationLabel = 'Blog posts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return BlogPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
