<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\ChamberCashEntry;
use Filament\Forms\Components\Checkbox;
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
}
