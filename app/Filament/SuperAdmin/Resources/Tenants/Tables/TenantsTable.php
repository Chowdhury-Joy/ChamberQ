<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Tables;

use App\Filament\SuperAdmin\Support\TenantBackupActions;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->label('Tenant ID'),
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('plan_tier')
                    ->label('Tier')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Tenant::planTierLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'clinic' => 'success',
                        'solo' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('modules')
                    ->label(__('Modules'))
                    ->state(fn (Tenant $record): string => implode(' · ', $record->productModuleChipLabels()) ?: '—'),
                TextColumn::make('marketer.display_name')
                    ->label(__('Marketer'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('setup_amount_due')
                    ->label(__('Setup due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—'),
                TextColumn::make('monthly_amount_due')
                    ->label(__('Monthly due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—'),
                TextColumn::make('billing_status')
                    ->label('Billing')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'past_due' => 'warning',
                        'suspended', 'read_only' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sms_balance')
                    ->label('SMS')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('domains_count')
                    ->counts('domains')
                    ->label('Domains'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                TenantBackupActions::downloadAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
