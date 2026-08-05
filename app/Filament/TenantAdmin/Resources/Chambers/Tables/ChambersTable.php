<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChambersTable
{
    /**
     * No bulk delete here: `ChamberPolicy` has to keep at least one chamber
     * alive for bookings and schedules, and a bulk action is authorized once for
     * the whole selection — against a count taken before any row is removed — so
     * that rule cannot hold. Chambers are deleted one at a time from Edit, where
     * `ChamberPolicy::delete()` applies.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('map_url')
                    ->label(__('Google Maps link'))
                    ->placeholder(__('Uses address'))
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(30),
                TextColumn::make('contact')
                    ->searchable(),
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
            ]);
    }
}
