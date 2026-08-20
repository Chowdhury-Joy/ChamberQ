<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\ChamberCashEntry;
use App\Models\PharmacyItem;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

final class PharmacyPaymentFields
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(bool $allowWaive = false): array
    {
        $fields = [
            Select::make('method')
                ->label(__('Paid how'))
                ->options(ChamberCashEntry::methods())
                ->default(ChamberCashEntry::METHOD_CASH)
                ->required()
                ->live()
                ->native(false)
                ->visible(fn (Get $get): bool => ! $allowWaive || ! (bool) $get('waived')),
            TextInput::make('discount_taka')
                ->label(__('Discount (৳)'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->live()
                ->visible(fn (Get $get): bool => ! $allowWaive || ! (bool) $get('waived')),
            Placeholder::make('to_collect')
                ->label(__('To collect'))
                ->content(function (Get $get) use ($allowWaive): string {
                    if ($allowWaive && (bool) $get('waived')) {
                        return __('Waived');
                    }

                    $basket = self::basketTotal($get);
                    $discount = max(0, (int) ($get('discount_taka') ?? 0));
                    $due = max(0, $basket - $discount);

                    return '৳'.number_format($due);
                }),
            TextInput::make('cash_taka')
                ->label(__('Cash (৳)'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                    && (! $allowWaive || ! (bool) $get('waived'))),
            TextInput::make('online_taka')
                ->label(__('Online (৳)'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                    && (! $allowWaive || ! (bool) $get('waived'))),
            Select::make('online_method')
                ->label(__('Online method'))
                ->options(ChamberCashEntry::onlineMethods())
                ->native(false)
                ->visible(fn (Get $get): bool => $get('method') === ChamberCashEntry::METHOD_MIXED
                    && (! $allowWaive || ! (bool) $get('waived'))
                    && (int) ($get('online_taka') ?? 0) > 0),
        ];

        if ($allowWaive) {
            array_unshift($fields, Checkbox::make('waived')->label(__('Give free (waive)'))->live());
        }

        $fields[] = TextInput::make('note')->label(__('Note'));

        return $fields;
    }

    private static function basketTotal(Get $get): int
    {
        $total = 0;
        foreach ($get('lines') ?? [] as $line) {
            $id = (int) ($line['pharmacy_item_id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($id < 1 || $qty < 1) {
                continue;
            }
            $price = (int) PharmacyItem::query()->whereKey($id)->value('sell_price_taka');
            $total += $price * $qty;
        }

        return $total;
    }
}
