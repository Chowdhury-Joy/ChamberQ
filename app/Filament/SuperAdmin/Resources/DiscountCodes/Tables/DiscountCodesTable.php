<?php

namespace App\Filament\SuperAdmin\Resources\DiscountCodes\Tables;

use App\Models\DiscountCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiscountCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->placeholder('—'),
                TextColumn::make('setup_percent')
                    ->label(__('Setup %'))
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('monthly_percent')
                    ->label(__('Monthly %'))
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('marketer.display_name')
                    ->label(__('Marketer'))
                    ->placeholder('—'),
                TextColumn::make('redemption_count')
                    ->label(__('Used')),
                IconColumn::make('is_active')
                    ->boolean(),
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
