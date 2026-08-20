<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\PharmacyPaymentFields;
use App\Models\PharmacyItem;
use App\Services\PharmacyStockService;
use App\Services\PharmacySupplierService;
use App\Support\PharmacyAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;

class PharmacyPaySupplier extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Pay supplier';

    protected static ?string $title = 'Pay supplier';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.tenant-admin.pages.pharmacy-pay-supplier';

    /** @var array{owed: int, refund_due: int, doctor_pending: int} */
    public array $balance = ['owed' => 0, 'refund_due' => 0, 'doctor_pending' => 0];

    public static function canAccess(): bool
    {
        return PharmacyAccess::canRunCounter(auth()->user());
    }

    public function mount(): void
    {
        $this->refreshBalance();
    }

    public function refreshBalance(): void
    {
        $this->balance = app(PharmacySupplierService::class)->shopBalance(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pay')
                ->label(__('Pay supplier'))
                ->color('danger')
                ->form([
                    TextInput::make('amount')
                        ->label(__('Amount (৳)'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(fn (): int => $this->balance['owed']),
                    ...PharmacyPaymentFields::components(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(PharmacySupplierService::class)->pay(
                            auth()->user(),
                            (int) $data['amount'],
                            (string) $data['method'],
                            $data['note'] ?? null,
                            null,
                            isset($data['cash_taka']) ? (int) $data['cash_taka'] : null,
                            isset($data['online_taka']) ? (int) $data['online_taka'] : null,
                            $data['online_method'] ?? null,
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    $this->refreshBalance();
                    Notification::make()->title(__('Supplier payment recorded'))->success()->send();
                }),
            Action::make('refund')
                ->label(__('Record supplier refund'))
                ->form([
                    TextInput::make('amount')
                        ->label(__('Amount received (৳)'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(fn (): int => $this->balance['refund_due']),
                    ...PharmacyPaymentFields::components(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(PharmacySupplierService::class)->recordRefund(
                            auth()->user(),
                            (int) $data['amount'],
                            (string) $data['method'],
                            $data['note'] ?? null,
                            null,
                            isset($data['cash_taka']) ? (int) $data['cash_taka'] : null,
                            isset($data['online_taka']) ? (int) $data['online_taka'] : null,
                            $data['online_method'] ?? null,
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    $this->refreshBalance();
                    Notification::make()->title(__('Supplier refund recorded'))->success()->send();
                }),
            Action::make('returnUnsold')
                ->label(__('Return unsold'))
                ->form([
                    Select::make('pharmacy_item_id')
                        ->label(__('Medicine'))
                        ->options(fn (): array => PharmacyAccess::scopedItems(auth()->user())
                            ->where('qty_on_hand', '>', 0)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (PharmacyItem $item): array => [$item->id => $item->displayLabel()])
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                    TextInput::make('qty')
                        ->label(__('How many going back'))
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('note')->label(__('Note')),
                ])
                ->action(function (array $data): void {
                    $item = PharmacyAccess::scopedItems(auth()->user())->find($data['pharmacy_item_id']);
                    if (! $item) {
                        return;
                    }
                    try {
                        app(PharmacyStockService::class)->returnUnsold($item, auth()->user(), (int) $data['qty'], $data['note'] ?? null);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    $this->refreshBalance();
                    Notification::make()->title(__('Unsold returned'))->success()->send();
                }),
        ];
    }
}
