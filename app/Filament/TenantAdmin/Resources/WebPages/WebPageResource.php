<?php

namespace App\Filament\TenantAdmin\Resources\WebPages;

use App\Filament\TenantAdmin\Resources\WebPages\Pages\CreateWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\EditWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\ListWebPages;
use App\Filament\TenantAdmin\Resources\WebPages\Schemas\WebPageForm;
use App\Filament\TenantAdmin\Resources\WebPages\Tables\WebPagesTable;
use App\Models\WebPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebPageResource extends Resource
{
    protected static ?string $model = WebPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->default('/'),
                Forms\Components\Toggle::make('is_published')
                    ->required()
                    ->default(true),
                Forms\Components\Builder::make('content')
                    ->blocks([
                        Forms\Components\Builder\Block::make('hero')
                            ->schema([
                                Forms\Components\TextInput::make('headline')->required(),
                                Forms\Components\TextInput::make('subheadline'),
                                Forms\Components\TextInput::make('cta_text')->default('Book Appointment'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                            ]),
                        Forms\Components\Builder\Block::make('rich_text')
                            ->schema([
                                Forms\Components\RichEditor::make('content')->required(),
                            ]),
                        Forms\Components\Builder\Block::make('doctors_list')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Our Doctors')->required(),
                            ]),
                        Forms\Components\Builder\Block::make('services')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Our Services')->required(),
                                Forms\Components\Repeater::make('items')
                                    ->schema([
                                        Forms\Components\TextInput::make('title')->required(),
                                        Forms\Components\TextInput::make('description'),
                                        Forms\Components\TextInput::make('icon'),
                                    ])
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return WebPagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebPages::route('/'),
            'create' => CreateWebPage::route('/create'),
            'edit' => EditWebPage::route('/{record}/edit'),
        ];
    }
}
