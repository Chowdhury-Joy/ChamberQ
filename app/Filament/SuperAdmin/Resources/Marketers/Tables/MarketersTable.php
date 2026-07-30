<?php

namespace App\Filament\SuperAdmin\Resources\Marketers\Tables;

use App\Models\Marketer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('Ref code'))
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (Marketer $record): string => $record->referralUrl()),
                TextColumn::make('user.email')
                    ->label(__('Login email'))
                    ->searchable(),
                TextColumn::make('tenants_count')
                    ->counts('tenants')
                    ->label(__('Doctors')),
                TextColumn::make('setup_commission_rate')
                    ->label(__('Setup %'))
                    ->formatStateUsing(fn ($state) => round((float) $state * 100).'%'),
                TextColumn::make('monthly_commission_rate')
                    ->label(__('Monthly %'))
                    ->formatStateUsing(fn ($state) => round((float) $state * 100).'%'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
