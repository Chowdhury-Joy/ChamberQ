<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DoctorsTable
{
    /**
     * No bulk delete here: `DoctorPolicy` stops a solo tenant removing the only
     * doctor every schedule and booking points at, and a bulk action is
     * authorized once for the whole selection, so that rule cannot hold. Doctors
     * are deleted one at a time from Edit, where `DoctorPolicy::delete()` applies.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
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
