<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\User;
use App\Services\ChamberCashService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

final class CollectFeeAction
{
    public static function make(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(function (Action $action, ...$args) use ($booking): string {
                $record = self::resolve($booking, [$action, ...$args]);

                return $record?->cashEntry ? 'Edit fee' : 'Collect fee';
            })
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->fillForm(function (Action $action, ...$args) use ($booking): array {
                $record = self::resolve($booking, [$action, ...$args]);
                if (! $record) {
                    return [];
                }

                if (tenant()?->hasStations()) {
                    return StationsCollectFeeForm::fillFromEntry($record);
                }

                $entry = $record->cashEntry;
                $doctor = Doctor::resolveForBooking($record);
                $feeType = $entry?->fee_type ?? Doctor::FEE_CONSULTATION;
                if ($doctor && ! array_key_exists($feeType, $doctor->feeTypes())) {
                    $feeType = Doctor::FEE_CONSULTATION;
                }

                return [
                    'fee_type' => $feeType,
                    'method' => $entry?->method ?? ChamberCashEntry::METHOD_CASH,
                    'cash_taka' => $entry?->cash_taka ?? 0,
                    'online_taka' => $entry?->mobile_taka ?? 0,
                    'online_method' => $entry?->mobile_method,
                    'waived' => $entry?->isWaived() ?? false,
                    'note' => $entry?->note,
                ];
            })
            ->form(function (Action $action, ...$args) use ($booking): array {
                $record = self::resolve($booking, [$action, ...$args]);
                if (! $record) {
                    return [];
                }

                if (tenant()?->hasStations()) {
                    return StationsCollectFeeForm::components($record);
                }

                $hasExtras = Doctor::resolveForBooking($record)?->hasExtraFeeTypes() ?? false;

                return [
                    $hasExtras
                        ? Select::make('fee_type')
                            ->label(__('Visit type'))
                            ->options(fn (): array => app(ChamberCashService::class)->feeTypeOptions($record))
                            ->required()
                            ->live()
                            ->native(false)
                        : Hidden::make('fee_type')
                            ->default(Doctor::FEE_CONSULTATION),
                    Placeholder::make('amount_due')
                        ->label(__('Amount'))
                        ->content(function (Get $get) use ($record): string {
                            $type = (string) ($get('fee_type') ?: Doctor::FEE_CONSULTATION);
                            try {
                                $taka = app(ChamberCashService::class)->amountForFeeType($record, $type);
                            } catch (\InvalidArgumentException) {
                                $taka = app(ChamberCashService::class)->suggestedAmountTaka($record);
                            }

                            return '৳'.number_format($taka);
                        })
                        ->helperText(__('Set on the doctor\'s fee list. Staff cannot type an amount.')),
                    Select::make('method')
                        ->label(__('Paid how'))
                        ->options(ChamberCashEntry::methods())
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('cash_taka')
                        ->label(__('Cash (৳)'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                            && ! (bool) $get('waived'))
                        ->rules([
                            fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                if ($get('method') !== ChamberCashEntry::METHOD_MIXED || (bool) $get('waived')) {
                                    return;
                                }

                                try {
                                    $fee = app(ChamberCashService::class)->amountForFeeType(
                                        $record,
                                        (string) ($get('fee_type') ?: Doctor::FEE_CONSULTATION),
                                    );
                                } catch (\InvalidArgumentException) {
                                    return;
                                }

                                if ((int) $get('cash_taka') + (int) $get('online_taka') !== $fee) {
                                    $fail(__('Cash plus online must equal the total amount.'));
                                }
                            },
                        ]),
                    TextInput::make('online_taka')
                        ->label(__('Online (৳)'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                            && ! (bool) $get('waived')),
                    Select::make('online_method')
                        ->label(__('Online method'))
                        ->options(ChamberCashEntry::onlineMethods())
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                            && ! (bool) $get('waived')
                            && (int) ($get('online_taka') ?? 0) > 0)
                        ->required(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                            && ! (bool) $get('waived')
                            && (int) ($get('online_taka') ?? 0) > 0),
                    Checkbox::make('waived')
                        ->label(__('Waive this fee'))
                        ->live(),
                    TextInput::make('note')
                        ->label(__('Note')),
                ];
            })
            ->action(function (array $data, Action $action, ?Booking $record = null, ...$args) use ($booking): void {
                $record = self::resolve($booking, [$record, $action, ...$args]);
                if (! $record) {
                    return;
                }

                /** @var User $user */
                $user = auth()->user();

                if (tenant()?->hasStations()) {
                    try {
                        StationsCollectFeeForm::save($record, $data, $user);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(($data['waived'] ?? false) ? __('Fee waived') : __('Fee collected'))
                        ->success()
                        ->send();

                    return;
                }

                try {
                    app(ChamberCashService::class)->recordPatientIncome(
                        $record,
                        $user,
                        $data['method'],
                        waived: (bool) ($data['waived'] ?? false),
                        note: filled($data['note'] ?? null) ? (string) $data['note'] : null,
                        feeType: (string) ($data['fee_type'] ?? Doctor::FEE_CONSULTATION),
                        cashTaka: isset($data['cash_taka']) ? (int) $data['cash_taka'] : null,
                        onlineTaka: isset($data['online_taka']) ? (int) $data['online_taka'] : null,
                        onlineMethod: filled($data['online_method'] ?? null) ? (string) $data['online_method'] : null,
                    );
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(($data['waived'] ?? false) ? __('Fee waived') : __('Fee collected'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  list<mixed>  $args
     */
    private static function resolve(?Closure $booking, array $args): ?Booking
    {
        if ($booking) {
            $resolved = $booking();

            return $resolved instanceof Booking ? $resolved : null;
        }

        foreach ($args as $arg) {
            if ($arg instanceof Booking) {
                return $arg;
            }

            if ($arg instanceof Action) {
                $mounted = $arg->getRecord();
                if ($mounted instanceof Booking) {
                    return $mounted;
                }
            }
        }

        return null;
    }
}
