<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chamber_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('doctor_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('day_of_week')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('session_name')
                    ->searchable(),
                TextColumn::make('kind')
                    ->label(__('Room type'))
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (\App\Models\ScheduleSession::kindOptions()[$state] ?? $state)
                        : '—')
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false),
                TextColumn::make('start_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('slot_cap')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
