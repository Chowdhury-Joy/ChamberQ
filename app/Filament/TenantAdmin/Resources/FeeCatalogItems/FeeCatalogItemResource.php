<?php

namespace App\Filament\TenantAdmin\Resources\FeeCatalogItems;

use App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages\CreateFeeCatalogItem;
use App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages\EditFeeCatalogItem;
use App\Filament\TenantAdmin\Resources\FeeCatalogItems\Pages\ListFeeCatalogItems;
use App\Models\FeeCatalogItem;
use App\Models\ScheduleSession;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeeCatalogItemResource extends Resource
{
    protected static ?string $model = FeeCatalogItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Fee catalogue';

    protected static ?string $modelLabel = 'Fee item';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->canManageCash() ?? false)
            && (tenant()?->hasStations() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('label')
                ->label(__('Label'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('Visit, Follow-up, MSK, PRP knee…')),
            Forms\Components\TextInput::make('list_price_taka')
                ->label(__('Board price (৳)'))
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\TextInput::make('house_share_taka')
                ->label(__('Clinic house share (৳)'))
                ->helperText(__('Taken from money collected, never from board price. Collected ৳0 → clinic ৳0.'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\Select::make('sitting_kind')
                ->label(__('Usually for'))
                ->options(FeeCatalogItem::sittingKindOptions())
                ->native(false)
                ->placeholder(__('Any')),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('Sort order'))
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('list_price_taka')
                    ->label(__('Board'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('house_share_taka')
                    ->label(__('House'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('sitting_kind')
                    ->label(__('Room'))
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (ScheduleSession::kindOptions()[$state] ?? $state)
                        : '—'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeCatalogItems::route('/'),
            'create' => CreateFeeCatalogItem::route('/create'),
            'edit' => EditFeeCatalogItem::route('/{record}/edit'),
        ];
    }
}
