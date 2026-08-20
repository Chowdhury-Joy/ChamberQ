<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyItems;

use App\Filament\TenantAdmin\Resources\PharmacyItems\Pages\CreatePharmacyItem;
use App\Filament\TenantAdmin\Resources\PharmacyItems\Pages\EditPharmacyItem;
use App\Filament\TenantAdmin\Resources\PharmacyItems\Pages\ListPharmacyItems;
use App\Models\ChamberCashEntry;
use App\Models\PharmacyItem;
use App\Models\User;
use App\Services\MedicineService;
use App\Services\PharmacyStockService;
use App\Support\PharmacyAccess;
use App\Support\StaffDeskScope;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class PharmacyItemResource extends Resource
{
    protected static ?string $model = PharmacyItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Pharmacy stock';

    protected static ?string $modelLabel = 'Shop item';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return PharmacyAccess::canManageStock(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canMutate($record);
    }

    public static function canEdit($record): bool
    {
        return static::canMutate($record);
    }

    public static function canDelete($record): bool
    {
        return static::canMutate($record);
    }

    private static function canMutate($record): bool
    {
        $user = auth()->user();

        return static::canViewAny()
            && $record instanceof PharmacyItem
            && $user instanceof User
            && StaffDeskScope::pharmacyItemIsVisible($user, $record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User) {
            StaffDeskScope::constrainPharmacyItems($query, $user);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('medicine_id')
                ->label(__('From catalogue'))
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return app(MedicineService::class)
                        ->search($search, auth()->user())
                        ->mapWithKeys(fn (array $row): array => [
                            $row['medicine_id'] ?? $row['id'] => $row['label'],
                        ])
                        ->all();
                })
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if (! filled($state)) {
                        return;
                    }
                    $medicine = \App\Models\Medicine::query()->find($state);
                    if ($medicine) {
                        $set('name', $medicine->displayLabel());
                        $set('generic_name', $medicine->generic_name);
                    }
                })
                ->helperText(__('Optional. Typing a name below is enough for a local cream or ORS.')),
            Forms\Components\Select::make('chamber_id')
                ->label(__('Centre'))
                ->options(fn (): array => StaffDeskScope::chamberOptionsFor(auth()->user()))
                ->default(function (): ?int {
                    $ids = array_keys(StaffDeskScope::chamberOptionsFor(auth()->user()));

                    return count($ids) === 1 ? (int) $ids[0] : null;
                })
                ->required(fn (): bool => StaffDeskScope::tenantHasMultipleChambers())
                ->visible(fn (): bool => StaffDeskScope::tenantHasMultipleChambers())
                ->disabled(fn (?string $operation): bool => $operation === 'edit')
                ->native(false)
                ->helperText(__('Each centre has its own cupboard.')),
            Forms\Components\TextInput::make('name')
                ->label(__('Name on the shelf'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('generic_name')
                ->label(__('Generic'))
                ->maxLength(255),
            Forms\Components\TextInput::make('sell_price_taka')
                ->label(__('Selling price (৳)'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->live(),
            Forms\Components\TextInput::make('company_share_taka')
                ->label(__('Buying price (৳)'))
                ->helperText(__('What the company is owed per bottle. Profit is selling minus buying.'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->live()
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        if ((int) $value > (int) $get('sell_price_taka')) {
                            $fail(__('Buying price cannot be more than the selling price.'));
                        }
                    },
                ]),
            Forms\Components\Placeholder::make('shop_cut')
                ->label(__('Profit'))
                ->content(fn (Get $get): string => '৳'.number_format(max(0, (int) $get('sell_price_taka') - (int) $get('company_share_taka')))),
            Forms\Components\Select::make('unit_label')
                ->label(__('Counted as'))
                ->options(PharmacyItem::unitOptions())
                ->default(PharmacyItem::UNIT_STRIP)
                ->required()
                ->native(false),
            Forms\Components\Toggle::make('is_active')
                ->label(__('On sale'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('chamber.name')
                    ->label(__('Centre'))
                    ->visible(fn (): bool => StaffDeskScope::tenantHasMultipleChambers())
                    ->sortable(),
                TextColumn::make('qty_on_hand')->label(__('Current stock'))->sortable(),
                TextColumn::make('sell_price_taka')
                    ->label(__('Selling price'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('company_share_taka')
                    ->label(__('Buying price'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('shop_cut')
                    ->label(__('Profit'))
                    ->state(fn (PharmacyItem $record): string => '৳'.number_format($record->shopCutTaka())),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('receive')
                    ->label(__('Receive'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->form([
                        Forms\Components\TextInput::make('qty')
                            ->label(__('How many arrived'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('company_share_taka')
                            ->label(__('Buying price this box (৳)'))
                            ->helperText(__('Leave as the item default unless this delivery has a different deal.'))
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('paid_now')
                            ->label(__('Paid the company now (৳)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\Select::make('method')
                            ->label(__('Paid how'))
                            ->options(ChamberCashEntry::methods())
                            ->default(ChamberCashEntry::METHOD_CASH)
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('returnable')
                            ->label(__('Company takes back unsold'))
                            ->default(true),
                        Forms\Components\Textarea::make('note')->label(__('Note')),
                    ])
                    ->fillForm(fn (PharmacyItem $record): array => [
                        'company_share_taka' => $record->company_share_taka,
                    ])
                    ->action(function (PharmacyItem $record, array $data): void {
                        try {
                            app(PharmacyStockService::class)->receive(
                                $record,
                                auth()->user(),
                                (int) $data['qty'],
                                (int) $data['paid_now'],
                                (bool) $data['returnable'],
                                filled($data['company_share_taka'] ?? null) ? (int) $data['company_share_taka'] : null,
                                $data['note'] ?? null,
                                null,
                                (string) $data['method'],
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Stock received'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacyItems::route('/'),
            'create' => CreatePharmacyItem::route('/create'),
            'edit' => EditPharmacyItem::route('/{record}/edit'),
        ];
    }
}
