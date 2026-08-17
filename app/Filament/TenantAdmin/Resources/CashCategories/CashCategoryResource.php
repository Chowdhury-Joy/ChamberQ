<?php

namespace App\Filament\TenantAdmin\Resources\CashCategories;

use App\Filament\TenantAdmin\Resources\CashCategories\Pages\ListCashCategories;
use App\Models\CashCategory;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashCategoryResource extends Resource
{
    protected static ?string $model = CashCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Cash categories';

    protected static ?string $modelLabel = 'Cash category';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255)
                ->disabled(fn (?CashCategory $record): bool => $record?->is_locked ?? false),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Show in cashbook dropdown'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_locked')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('System') : __('Custom'))
                    ->color(fn (bool $state): string => $state ? 'gray' : 'primary'),
                IconColumn::make('is_active')
                    ->label(__('Visible'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('No categories yet'))
            ->emptyStateDescription(__('Add a heading for money in or money out.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashCategories::route('/'),
        ];
    }
}
