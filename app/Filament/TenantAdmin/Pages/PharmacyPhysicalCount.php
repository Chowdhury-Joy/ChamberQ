<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\PharmacyCount;
use App\Models\PharmacyItem;
use App\Models\User;
use App\Services\PharmacyStockService;
use App\Support\PharmacyAccess;
use App\Support\StaffDeskScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
        foreach ($this->scopedItems()->get() as $item) {
            $this->counted[$item->id] = $item->qty_on_hand;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->savedCountsQuery()->latest())
            ->columns([
                TextColumn::make('saved_at')->label(__('Saved'))->dateTime(),
                TextColumn::make('chamber.name')
                    ->label(__('Centre'))
                    ->visible(fn (): bool => StaffDeskScope::tenantHasMultipleChambers())
                    ->placeholder('—'),
                TextColumn::make('items_count')->counts('items')->label(__('Items')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $startable = $this->startableChamberOptions();
        $openCounts = $this->openCounts();

        $actions = [
            Action::make('start')
                ->label(__('Start count'))
                ->visible(fn (): bool => $startable !== [])
                ->form(
                    count($startable) > 1
                        ? [
                            Select::make('chamber_id')
                                ->label(__('Centre'))
                                ->options($startable)
                                ->required()
                                ->native(false),
                        ]
                        : []
                )
                ->action(function (array $data) use ($startable): void {
                    $chamberId = filled($data['chamber_id'] ?? null)
                        ? (int) $data['chamber_id']
                        : (int) array_key_first($startable);

                    try {
                        app(PharmacyStockService::class)->startCount(auth()->user(), $chamberId);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];

        foreach ($openCounts as $open) {
            $items = $this->itemsForCount($open);
            $label = StaffDeskScope::tenantHasMultipleChambers() && $open->chamber
                ? __('Save count — :centre', ['centre' => $open->chamber->name])
                : __('Save count');

            $actions[] = Action::make('saveCount_'.$open->id)
                ->label($label)
                ->form(
                    $items->map(
                        fn (PharmacyItem $item) => TextInput::make('counted.'.$item->id)
                            ->label($item->name.' ('.__('system').' '.$item->qty_on_hand.')')
                            ->numeric()
                            ->minValue(0)
                            ->default($item->qty_on_hand)
                            ->required(),
                    )->all()
                )
                ->action(function (array $data) use ($open): void {
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
                });
        }

        return $actions;
    }

    private function scopedItems(): Builder
    {
        return PharmacyAccess::scopedItems(auth()->user())->orderBy('name');
    }

    /** @return Collection<int, PharmacyCount> */
    private function openCounts(): Collection
    {
        return $this->countsQuery()
            ->where('status', PharmacyCount::STATUS_IN_PROGRESS)
            ->with('chamber')
            ->orderBy('id')
            ->get();
    }

    private function savedCountsQuery(): Builder
    {
        return $this->countsQuery()->where('status', PharmacyCount::STATUS_SAVED);
    }

    private function countsQuery(): Builder
    {
        $query = PharmacyCount::query();
        $user = auth()->user();
        if ($user instanceof User) {
            $ids = StaffDeskScope::chamberIdsFor($user);
            if ($ids !== null) {
                $query->whereIn('chamber_id', $ids);
            }
        }

        return $query;
    }

    /** @return array<int, string> */
    private function startableChamberOptions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $busy = $this->openCounts()->pluck('chamber_id')->map(fn ($id): int => (int) $id)->all();
        $options = StaffDeskScope::chamberOptionsFor($user);

        return array_filter(
            $options,
            fn ($name, $id): bool => ! in_array((int) $id, $busy, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @return Collection<int, PharmacyItem> */
    private function itemsForCount(PharmacyCount $count): Collection
    {
        $query = $this->scopedItems();
        if ($count->chamber_id) {
            $query->where('chamber_id', $count->chamber_id);
        }

        return $query->get();
    }
}
