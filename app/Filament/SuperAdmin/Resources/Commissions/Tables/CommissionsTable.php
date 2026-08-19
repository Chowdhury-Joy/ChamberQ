<?php

namespace App\Filament\SuperAdmin\Resources\Commissions\Tables;

use App\Models\Commission;
use App\Services\CommissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payee')
                    ->label(__('Payee'))
                    ->state(fn (Commission $record): string => $record->payeeName())
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('marketer', fn ($q) => $q->where('display_name', 'like', "%{$search}%"))
                            ->orWhereHas('medicalRepresentative', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('tenant.name')
                    ->label(__('Doctor / tenant'))
                    ->placeholder(fn (Commission $record) => $record->tenant_id)
                    ->searchable(),
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
                    ->label(__('Commission'))
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
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('marketer_id')
                    ->label(__('Marketer'))
                    ->relationship('marketer', 'display_name'),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label(__('Mark payout paid'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Commission $record) => $record->status === Commission::STATUS_OWED)
                    ->schema([
                        TextInput::make('payout_note')
                            ->label(__('bKash trx / note'))
                            ->maxLength(255),
                    ])
                    ->action(function (Commission $record, array $data, CommissionService $commissions): void {
                        $commissions->markCommissionPaid($record, $data['payout_note'] ?? null);

                        Notification::make()
                            ->title(__('Commission marked paid'))
                            ->success()
                            ->send();
                    }),
                Action::make('void')
                    ->label(__('Void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Commission $record) => in_array($record->status, [Commission::STATUS_PENDING, Commission::STATUS_OWED], true))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('Reason'))
                            ->rows(2),
                    ])
                    ->action(function (Commission $record, array $data, CommissionService $commissions): void {
                        $commissions->voidCommission($record, $data['note'] ?? null);

                        Notification::make()
                            ->title(__('Commission voided'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
