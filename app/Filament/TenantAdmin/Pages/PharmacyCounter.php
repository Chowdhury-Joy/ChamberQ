<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\PharmacyPaymentFields;
use App\Models\ChamberCashEntry;
use App\Models\PharmacyItem;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\User;
use App\Services\OperationalReportService;
use App\Services\PharmacySaleService;
use App\Support\PharmacyAccess;
use App\Support\StaffDeskScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PharmacyCounter extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Pharmacy';

    protected static ?string $title = 'Pharmacy';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.tenant-admin.pages.pharmacy-counter';

    public static function canAccess(): bool
    {
        return PharmacyAccess::canRunCounter(auth()->user());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = PharmacySale::query()->with('items')->latest();
                $user = auth()->user();
                if ($user instanceof User) {
                    $ids = StaffDeskScope::chamberIdsFor($user);
                    if ($ids !== null) {
                        $query->whereHas('items.item', fn ($item) => $item->whereIn('chamber_id', $ids));
                    }
                }

                return $query;
            })
            ->columns([
                TextColumn::make('occurred_on')->label(__('Date'))->date(),
                TextColumn::make('patient_name')->label(__('Patient'))->searchable()->placeholder(__('Walk-in')),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (PharmacySale $record): string => $record->waived
                        ? __('Waived')
                        : '৳'.number_format($record->amount)),
                TextColumn::make('medicine_types')
                    ->label(__('Type of medicine'))
                    ->getStateUsing(fn (PharmacySale $record): string => $record->items
                        ->map(fn ($line) => $line->qty > 1 ? $line->name.' × '.$line->qty : $line->name)
                        ->filter()
                        ->implode(', '))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('voided_at')
                    ->label(__('Returned'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label(__('Receipt'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (PharmacySale $record): string => tenant_web_route(
                        'pharmacy-invoices.show',
                        ['sale' => $record],
                        absolute: false,
                    ))
                    ->openUrlInNewTab(),
                Action::make('void')
                    ->label(__('Return'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PharmacySale $record): bool => ! $record->isVoided())
                    ->action(function (PharmacySale $record): void {
                        try {
                            app(PharmacySaleService::class)->void($record, auth()->user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Sale returned'))->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sellFromRx')
                ->label(__('Sell from prescription'))
                ->color('success')
                ->form($this->rxSaleForm())
                ->action(fn (array $data) => $this->submitSale($data, fromRx: true)),
            Action::make('walkIn')
                ->label(__('Walk-in sale'))
                ->form($this->walkInForm())
                ->action(fn (array $data) => $this->submitSale($data, fromRx: false)),
        ];
    }

    /** @return list<Component> */
    private function rxSaleForm(): array
    {
        return [
            Select::make('prescription_id')
                ->label(__('Today\'s prescription'))
                ->options(fn (): array => $this->todayPrescriptionOptions())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if (! filled($state)) {
                        return;
                    }
                    $rx = Prescription::query()->with('items')->find($state);
                    if (! $rx) {
                        return;
                    }
                    $lines = [];
                    foreach (app(PharmacySaleService::class)->suggestionsFromPrescription($rx) as $row) {
                        if (! $row['matched'] || $row['qty_on_hand'] < 1) {
                            continue;
                        }
                        $qty = $row['suggested_qty'] ?? 1;
                        $lines[] = [
                            'pharmacy_item_id' => $row['pharmacy_item_id'],
                            'prescription_item_id' => $row['prescription_item_id'],
                            'qty' => max(1, min((int) $qty, $row['qty_on_hand'])),
                        ];
                    }
                    $set('lines', $lines);
                }),
            $this->linesRepeater(),
            ...PharmacyPaymentFields::components(allowWaive: true),
        ];
    }

    /** @return list<Component> */
    private function walkInForm(): array
    {
        return [
            TextInput::make('patient_name')->label(__('Name (optional)')),
            $this->linesRepeater(),
            ...PharmacyPaymentFields::components(allowWaive: true),
        ];
    }

    private function linesRepeater(): Repeater
    {
        return Repeater::make('lines')
            ->label(__('Basket'))
            ->schema([
                Select::make('pharmacy_item_id')
                    ->label(__('Medicine'))
                    ->options(fn (): array => PharmacyAccess::scopedItems(auth()->user())
                        ->where('is_active', true)
                        ->where('qty_on_hand', '>', 0)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (PharmacyItem $item): array => [$item->id => $item->displayLabel()])
                        ->all())
                    ->required()
                    ->searchable()
                    ->native(false),
                TextInput::make('qty')
                    ->label(__('Qty'))
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(1),
                TextInput::make('prescription_item_id')->hidden(),
            ])
            ->columns(2)
            ->required()
            ->minItems(1)
            ->addActionLabel(__('Add item'));
    }

    /** @param  array<string, mixed>  $data */
    private function submitSale(array $data, bool $fromRx): void
    {
        /** @var User $user */
        $user = auth()->user();
        $prescription = $fromRx && filled($data['prescription_id'] ?? null)
            ? Prescription::query()->find($data['prescription_id'])
            : null;

        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = [
                'pharmacy_item_id' => (int) $line['pharmacy_item_id'],
                'qty' => (int) $line['qty'],
                'prescription_item_id' => $line['prescription_item_id'] ?? null,
            ];
        }

        $patientName = filled($data['patient_name'] ?? null) ? trim((string) $data['patient_name']) : null;
        if ($patientName === '') {
            $patientName = null;
        }

        try {
            $sale = app(PharmacySaleService::class)->sell(
                $user,
                $lines,
                (string) ($data['method'] ?? ChamberCashEntry::METHOD_CASH),
                (bool) ($data['waived'] ?? false),
                $prescription,
                $patientName,
                null,
                $data['note'] ?? null,
                isset($data['cash_taka']) ? (int) $data['cash_taka'] : null,
                isset($data['online_taka']) ? (int) $data['online_taka'] : null,
                $data['online_method'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('Sale recorded'))->success()->send();
        $this->resetTable();
        $this->js('window.open('.json_encode(tenant_web_route(
            'pharmacy-invoices.show',
            ['sale' => $sale],
            absolute: false,
        )).', "_blank")');
    }

    /** @return array<string, string> */
    private function todayPrescriptionOptions(): array
    {
        $today = now(OperationalReportService::TIMEZONE)->toDateString();

        return Prescription::query()
            ->whereHas('items')
            ->whereHas('visitRecord.booking', function ($q) use ($today): void {
                $q->whereDate('booking_date', $today);
                $user = auth()->user();
                if ($user instanceof User) {
                    StaffDeskScope::constrainBookings($q, $user);
                }
            })
            ->with(['patient', 'visitRecord.booking'])
            ->latest()
            ->get()
            ->mapWithKeys(function (Prescription $rx): array {
                $name = $rx->patient?->name ?? __('Patient');
                $serial = $rx->visitRecord?->booking?->serial_number;

                return [$rx->id => trim($name.' '.$serial)];
            })
            ->all();
    }
}
