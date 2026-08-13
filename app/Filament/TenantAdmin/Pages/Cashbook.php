<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\User;
use App\Services\ChamberCashService;
use App\Services\OperationalReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Cashbook extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Cashbook';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Cashbook';

    protected string $view = 'filament.tenant-admin.pages.cashbook';

    /** @var 'day'|'week'|'month' */
    public string $period = 'day';

    public string $anchorDate = '';

    /** @var array{income: int, expense: int, net: int, waived_count: int, waived_amount: int}|null */
    protected ?array $summaryCache = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canManageCash() ?? false;
    }

    public function mount(): void
    {
        $this->anchorDate = now(OperationalReportService::TIMEZONE)->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->summaryCache = null;
        $this->resetTable();
    }

    public function updatedAnchorDate(): void
    {
        $this->summaryCache = null;
        $this->resetTable();
    }

    public function getAnchor(): Carbon
    {
        $date = filled($this->anchorDate)
            ? $this->anchorDate
            : now(OperationalReportService::TIMEZONE)->toDateString();

        return Carbon::parse($date, OperationalReportService::TIMEZONE)->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $service = app(OperationalReportService::class);
        $anchor = $this->getAnchor();

        return match ($this->period) {
            'week' => $service->weekRange($anchor),
            'month' => $service->monthRange($anchor),
            default => [$anchor, $anchor],
        };
    }

    /**
     * @return array{income: int, expense: int, net: int, waived_count: int, waived_amount: int}
     */
    public function getSummary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        [$from, $to] = $this->range();

        return $this->summaryCache = app(ChamberCashService::class)->summaryForRange($from, $to);
    }

    public static function formatTaka(int $amount): string
    {
        return '৳'.number_format($amount);
    }

    public function getPeriodLabel(): string
    {
        $anchor = $this->getAnchor();
        $service = app(OperationalReportService::class);

        if ($this->period === 'week') {
            [$start, $end] = $service->weekRange($anchor);

            return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
        }

        if ($this->period === 'month') {
            return $anchor->translatedFormat('F Y');
        }

        return $anchor->translatedFormat('l, j F Y');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->entriesQuery())
            ->columns([
                TextColumn::make('occurred_on')
                    ->label(__('Date'))
                    ->date(),
                TextColumn::make('direction')
                    ->label(__('In / out'))
                    ->badge()
                    ->formatStateUsing(function (string $state, ChamberCashEntry $record): string {
                        if ($record->isWaived()) {
                            return __('Waived');
                        }

                        return $state === ChamberCashEntry::DIRECTION_INCOME
                            ? __('Income')
                            : __('Expense');
                    })
                    ->color(fn (ChamberCashEntry $record): string => $record->isWaived()
                        ? 'warning'
                        : ($record->direction === ChamberCashEntry::DIRECTION_INCOME ? 'success' : 'danger')),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => self::formatTaka($state)),
                TextColumn::make('category')
                    ->label(__('What'))
                    ->formatStateUsing(function (string $state, ChamberCashEntry $record): string {
                        $labels = $record->isIncome()
                            ? ChamberCashEntry::incomeCategories()
                            : ChamberCashEntry::expenseCategories();

                        return $labels[$state] ?? $state;
                    }),
                TextColumn::make('method')
                    ->label(__('How'))
                    ->formatStateUsing(fn (?string $state): string => ChamberCashEntry::methods()[$state] ?? '—'),
                TextColumn::make('booking.patient_name')
                    ->label(__('Patient'))
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->emptyStateHeading(__('No cash recorded for this period yet.'))
            ->emptyStateDescription(__('Collect a patient fee on Daily Roster, or add an expense here.'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addIncome')
                ->label(__('Add income'))
                ->icon('heroicon-o-plus')
                ->color('success')
                ->form($this->moneyForm(expense: false))
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    app(ChamberCashService::class)->recordOtherIncome(
                        $user,
                        (int) $data['amount'],
                        $data['method'],
                        Carbon::parse($data['occurred_on'], OperationalReportService::TIMEZONE),
                        filled($data['chamber_id']) ? (int) $data['chamber_id'] : null,
                        $data['note'] ?: null,
                    );

                    $this->summaryCache = null;
                    $this->resetTable();

                    Notification::make()
                        ->title(__('Income recorded'))
                        ->success()
                        ->send();
                }),
            Action::make('addExpense')
                ->label(__('Add expense'))
                ->icon('heroicon-o-minus')
                ->color('danger')
                ->form($this->moneyForm(expense: true))
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    app(ChamberCashService::class)->recordExpense(
                        $user,
                        (int) $data['amount'],
                        $data['category'],
                        $data['method'],
                        Carbon::parse($data['occurred_on'], OperationalReportService::TIMEZONE),
                        filled($data['chamber_id']) ? (int) $data['chamber_id'] : null,
                        $data['note'] ?: null,
                    );

                    $this->summaryCache = null;
                    $this->resetTable();

                    Notification::make()
                        ->title(__('Expense recorded'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private function moneyForm(bool $expense): array
    {
        $fields = [
            TextInput::make('amount')
                ->label(__('Amount (৳)'))
                ->numeric()
                ->minValue(1)
                ->required(),
        ];

        if ($expense) {
            $fields[] = Select::make('category')
                ->label(__('Category'))
                ->options(ChamberCashEntry::expenseCategories())
                ->required()
                ->native(false);
        }

        $fields[] = Select::make('method')
            ->label(__('Paid how'))
            ->options(ChamberCashEntry::methods())
            ->default(ChamberCashEntry::METHOD_CASH)
            ->required()
            ->native(false);
        $fields[] = DatePicker::make('occurred_on')
            ->label(__('Date'))
            ->default(fn (): string => $this->getAnchor()->toDateString())
            ->required();
        $fields[] = Select::make('chamber_id')
            ->label(__('Chamber'))
            ->options(fn (): array => Chamber::query()->orderBy('name')->pluck('name', 'id')->all())
            ->native(false);
        $fields[] = Textarea::make('note')
            ->label(__('Note'))
            ->rows(2);

        return $fields;
    }

    private function entriesQuery(): Builder
    {
        [$from, $to] = $this->range();

        return ChamberCashEntry::query()
            ->with(['booking'])
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<=', $to->toDateString())
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at');
    }
}
