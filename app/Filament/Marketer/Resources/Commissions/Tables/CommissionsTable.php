<?php

namespace App\Filament\Marketer\Resources\Commissions\Tables;

use App\Models\Commission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label(__('Doctor'))
                    ->placeholder(fn (Commission $record) => $record->tenant_id),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Commission::TYPE_YEAR_PREPAID => 'Year prepaid',
                        default => ucfirst($state),
                    }),
                TextColumn::make('period')
                    ->placeholder('—'),
                TextColumn::make('base_amount')
                    ->label(__('Doctor paid'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
                TextColumn::make('rate')
                    ->formatStateUsing(fn ($state) => round((float) $state * 100).'%'),
                TextColumn::make('commission_amount')
                    ->label(__('Your cut'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Commission::STATUS_PENDING => 'gray',
                        Commission::STATUS_OWED => 'warning',
                        Commission::STATUS_PAID => 'success',
                        Commission::STATUS_VOID => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Commission::STATUS_PENDING => 'Pending doctor payment',
                        Commission::STATUS_OWED => 'Owed',
                        Commission::STATUS_PAID => 'Paid',
                        Commission::STATUS_VOID => 'Void',
                        default => $state,
                    }),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Commission::STATUS_PENDING => 'Pending doctor payment',
                        Commission::STATUS_OWED => 'Owed',
                        Commission::STATUS_PAID => 'Paid',
                        Commission::STATUS_VOID => 'Void',
                    ]),
            ]);
    }
}
