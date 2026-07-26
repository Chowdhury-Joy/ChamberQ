<?php

namespace App\Filament\TenantAdmin\Resources\LabTests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LabTestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_order')
                    ->label(__('Order'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Test name'))
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('sample_type')
                    ->label(__('Sample'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('turnaround_time')
                    ->label(__('Report ready in'))
                    ->placeholder('—'),
                // Surfaced so staff can see at a glance which tests still have
                // no preparation text — a blank one reaches the patient.
                IconColumn::make('has_preparation')
                    ->label(__('Prep info'))
                    ->boolean()
                    ->state(fn ($record) => filled($record->preparation_instructions)),
                IconColumn::make('is_active')
                    ->label(__('Bookable'))
                    ->boolean(),
            ])
            ->defaultSort('display_order')
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
