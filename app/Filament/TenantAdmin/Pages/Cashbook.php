<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\User;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use App\Filament\TenantAdmin\Resources\CashCategories\CashCategoryResource;
use App\Models\CashCategory;
use App\Services\CashCategoryService;
use App\Services\ChamberCashService;
use App\Services\OperationalReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
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

        return $user instanceof User && StaffDeskJobs::canCollectFee($user);
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
                TextColumn::make('booking.patient_name')
                    ->label(__('Patient'))
                    ->searchable()
                    ->placeholder('—')
                    ->weight(FontWeight::Medium),
                TextColumn::make('cashbook_subject')
                    ->label(__('Procedure'))
                    ->state(fn (ChamberCashEntry $record): string => $record->cashbookSubjectLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->whereHas('booking', fn (Builder $q) => $q->where('patient_name', 'like', "%{$search}%"))
                                ->orWhereHas('feeCatalogItem', fn (Builder $q) => $q->where('label', 'like', "%{$search}%"));
                        });
                    }),
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
                TextColumn::make('clinic_share_taka')
                    ->label(__('Clinic'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? self::formatTaka($state) : '—')
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false),
                TextColumn::make('doctor_share_taka')
                    ->label(__('Doctor'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? self::formatTaka($state) : '—')
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false),
                TextColumn::make('discount_taka')
                    ->label(__('Discount'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? self::formatTaka($state) : '—')
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false),
                TextColumn::make('method')
                    ->label(__('How'))
                    ->formatStateUsing(fn (ChamberCashEntry $record): string => $record->paymentMethodLabel()),
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
            Action::make('manageCategories')
                ->label(__('Manage categories'))
                ->icon('heroicon-o-tag')
                ->url(fn (): string => CashCategoryResource::getUrl())
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            Action::make('addIncome')
                ->label(__('Add income'))
                ->icon('heroicon-o-plus')
                ->color('success')
                ->form($this->moneyForm(expense: false))
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        $payment = $this->resolvePaymentFormData($data);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    app(ChamberCashService::class)->recordOtherIncome(
                        $user,
                        $payment['amount'],
                        $data['category'],
                        $payment['method'],
                        Carbon::parse($data['occurred_on'], OperationalReportService::TIMEZONE),
                        filled($data['chamber_id']) ? (int) $data['chamber_id'] : null,
                        $data['note'] ?: null,
                        $payment['cash_taka'],
                        $payment['online_taka'],
                        $payment['online_method'],
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

                    try {
                        $payment = $this->resolvePaymentFormData($data);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    app(ChamberCashService::class)->recordExpense(
                        $user,
                        $payment['amount'],
                        $data['category'],
                        $payment['method'],
                        Carbon::parse($data['occurred_on'], OperationalReportService::TIMEZONE),
                        filled($data['chamber_id']) ? (int) $data['chamber_id'] : null,
                        $data['note'] ?: null,
                        $payment['cash_taka'],
                        $payment['online_taka'],
                        $payment['online_method'],
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
     * @return list<Component>
     */
    private function moneyForm(bool $expense): array
    {
        $fields = [
            TextInput::make('amount')
                ->label(__('Amount (৳)'))
                ->numeric()
                ->minValue(1)
                ->required(fn (Get $get): bool => $get('method') !== ChamberCashEntry::METHOD_MIXED)
                ->visible(fn (Get $get): bool => $get('method') !== ChamberCashEntry::METHOD_MIXED),
        ];

        $categoryService = app(CashCategoryService::class);
        $categoryType = $expense ? CashCategory::TYPE_EXPENSE : CashCategory::TYPE_INCOME;

        $fields[] = Select::make('category')
            ->label(__('Category'))
            ->options(fn (): array => $categoryService->pickerOptions($categoryType))
            ->default(fn (): ?string => $expense
                ? null
                : (array_key_first($categoryService->pickerOptions(CashCategory::TYPE_INCOME)) ?: null))
            ->required()
            ->native(false);

        $fields[] = Select::make('method')
            ->label(__('Paid how'))
            ->options(ChamberCashEntry::methods())
            ->default(ChamberCashEntry::METHOD_CASH)
            ->required()
            ->live()
            ->native(false);
        $fields[] = TextInput::make('cash_taka')
            ->label(__('Cash (৳)'))
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live()
            ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED);
        $fields[] = TextInput::make('online_taka')
            ->label(__('Online (৳)'))
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live()
            ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED);
        $fields[] = Select::make('online_method')
            ->label(__('Online method'))
            ->options(ChamberCashEntry::onlineMethods())
            ->native(false)
            ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                && (int) ($get('online_taka') ?? 0) > 0);
        $fields[] = DatePicker::make('occurred_on')
            ->label(__('Date'))
            ->default(fn (): string => $this->getAnchor()->toDateString())
            ->required();
        $fields[] = Select::make('chamber_id')
            ->label(__('Chamber'))
            ->options(function (): array {
                $user = auth()->user();

                return $user instanceof User
                    ? StaffDeskScope::chamberOptionsFor($user)
                    : Chamber::query()->orderBy('name')->pluck('name', 'id')->all();
            })
            ->native(false);
        $fields[] = Textarea::make('note')
            ->label(__('Note'))
            ->rows(2);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     amount: int,
     *     method: string,
     *     cash_taka: ?int,
     *     online_taka: ?int,
     *     online_method: ?string
     * }
     */
    private function resolvePaymentFormData(array $data): array
    {
        $method = (string) $data['method'];

        if ($method === ChamberCashEntry::METHOD_MIXED) {
            $cash = max(0, (int) ($data['cash_taka'] ?? 0));
            $online = max(0, (int) ($data['online_taka'] ?? 0));
            $amount = $cash + $online;

            if ($amount < 1) {
                throw new \InvalidArgumentException(__('Enter cash and/or online amounts.'));
            }

            if ($online > 0 && blank($data['online_method'] ?? null)) {
                throw new \InvalidArgumentException(__('Pick how the online part was paid.'));
            }

            return [
                'amount' => $amount,
                'method' => $method,
                'cash_taka' => $cash,
                'online_taka' => $online,
                'online_method' => $online > 0 ? (string) $data['online_method'] : null,
            ];
        }

        $amount = (int) ($data['amount'] ?? 0);

        if ($amount < 1) {
            throw new \InvalidArgumentException(__('Amount must be at least ৳1.'));
        }

        return [
            'amount' => $amount,
            'method' => $method,
            'cash_taka' => null,
            'online_taka' => null,
            'online_method' => null,
        ];
    }

    private function entriesQuery(): Builder
    {
        [$from, $to] = $this->range();

        $query = ChamberCashEntry::query()
            ->with(['booking', 'feeCatalogItem'])
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<=', $to->toDateString())
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at');

        $user = auth()->user();
        if ($user instanceof User) {
            StaffDeskScope::constrainCashEntries($query, $user);
        }

        return $query;
    }
}
