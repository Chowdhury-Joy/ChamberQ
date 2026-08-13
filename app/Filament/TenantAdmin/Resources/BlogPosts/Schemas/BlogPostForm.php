<?php

namespace App\Filament\TenantAdmin\Resources\BlogPosts\Schemas;

use App\Filament\TenantAdmin\Support\PublicMediaFields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set, $get): void {
                        if (blank($get('slug'))) {
                            $set('slug', \Illuminate\Support\Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(
                        table: 'blog_posts',
                        column: 'slug',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', tenant('id')),
                    ),
                Textarea::make('excerpt')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->columnSpanFull(),
                PublicMediaFields::image(
                    'image_url',
                    'blog-images',
                    __('Featured image'),
                    __('Upload a photo from this computer (JPG, PNG, or WebP, up to 4 MB). An older pasted link still works until you replace it.'),
                )->columnSpanFull(),
                DateTimePicker::make('published_at')
                    ->seconds(false),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_published')
                    ->default(false),
            ]);
    }
}
