<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\PharmacyCount;
use App\Models\PharmacyItem;
use App\Services\PharmacyStockService;
use App\Support\PharmacyAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PharmacyPhysicalCount extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Physical count';

    protected static ?string $title = 'Physical count';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.tenant-admin.pages.pharmacy-physical-count';

    /** @var array<int, int> */
    public array $counted = [];

    public static function canAccess(): bool
    {
        return PharmacyAccess::canManageStock(auth()->user());
    }

    public function mount(): void
    {
        foreach (PharmacyItem::query()->orderBy('name')->get() as $item) {
            $this->counted[$item->id] = $item->qty_on_hand;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PharmacyCount::query()->where('status', PharmacyCount::STATUS_SAVED)->latest())
            ->columns([
                TextColumn::make('saved_at')->label(__('Saved'))->dateTime(),
                TextColumn::make('items_count')->counts('items')->label(__('Items')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $open = PharmacyCount::query()->where('status', PharmacyCount::STATUS_IN_PROGRESS)->first();

        return [
            Action::make('start')
                ->label(__('Start count'))
                ->visible(fn (): bool => $open === null)
                ->action(function (): void {
                    try {
                        app(PharmacyStockService::class)->startCount(auth()->user());
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('saveCount')
                ->label(__('Save count'))
                ->visible(fn (): bool => $open !== null)
                ->form(
                    PharmacyItem::query()->orderBy('name')->get()->map(
                        fn (PharmacyItem $item) => TextInput::make('counted.'.$item->id)
                            ->label($item->name.' ('.__('system').' '.$item->qty_on_hand.')')
                            ->numeric()
                            ->minValue(0)
                            ->default($item->qty_on_hand)
                            ->required(),
                    )->all()
                )
                ->action(function (array $data) use ($open): void {
                    if (! $open) {
                        return;
                    }
                    $counted = [];
                    foreach ($data['counted'] ?? [] as $id => $qty) {
                        $counted[(int) $id] = (int) $qty;
                    }
                    try {
                        app(PharmacyStockService::class)->saveCount($open, auth()->user(), $counted);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('Count saved'))->success()->send();
                }),
        ];
    }
}
