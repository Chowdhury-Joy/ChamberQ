<?php

namespace App\Filament\Marketer\Resources\ReferredTenants\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferredTenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Clinic / doctor'))
                    ->placeholder(fn ($record) => $record->id)
                    ->searchable(),
                TextColumn::make('plan_tier')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ucfirst((string) $state)),
                TextColumn::make('billing_status')
                    ->badge()
                    ->label(__('Status')),
                TextColumn::make('setup_amount_due')
                    ->label(__('Setup due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—'),
                TextColumn::make('monthly_amount_due')
                    ->label(__('Monthly due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—'),
                TextColumn::make('setup_paid_at')
                    ->label(__('Setup paid'))
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('referral_note')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referred_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('referred_at', 'desc');
    }
}
