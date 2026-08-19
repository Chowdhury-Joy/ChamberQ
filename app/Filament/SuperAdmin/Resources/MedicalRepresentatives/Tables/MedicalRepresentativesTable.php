<?php

namespace App\Filament\SuperAdmin\Resources\MedicalRepresentatives\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicalRepresentativesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('phone')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
