<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Tables;

use App\Filament\SuperAdmin\Support\TenantBackupActions;
use App\Models\Tenant;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->placeholder('—')
                    // Without this the longest practice name sets a ~290px min-content
                    // width, which alone overflowed a phone-width table.
                    ->wrap(),
                TextColumn::make('plan_tier')
                    ->label('Tier')
                    ->badge()
                    ->visibleFrom('sm')
                    ->formatStateUsing(fn (?string $state): string => Tenant::planTierLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'clinic' => 'success',
                        'solo' => 'info',
                        default => 'gray',
                    }),
                // The sidebar eats ~380px, so `visibleFrom` (viewport-based) alone still
                // left the row actions off-screen at 1280. Everything below is either
                // available on tenant edit or on Client Health, so it starts hidden and
                // stays one toggle away rather than pushing Edit past the right edge.
                TextColumn::make('modules')
                    ->label(__('Modules'))
                    ->state(fn (Tenant $record): string => implode(' · ', $record->productModuleChipLabels()) ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('marketer.display_name')
                    ->label(__('Marketer'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('medicalRepresentative.name')
                    ->label(__('MR'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('setup_amount_due')
                    ->label(__('Setup due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('monthly_amount_due')
                    ->label(__('Monthly due'))
                    ->formatStateUsing(fn ($state) => $state ? '৳'.number_format((int) $state) : '—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_status')
                    ->label('Billing')
                    ->badge()
                    ->visibleFrom('sm')
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
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('domains_count')
                    ->counts('domains')
                    ->label('Domains')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan_tier')
                    ->label(__('Tier'))
                    ->options([
                        'solo' => Tenant::planTierLabel('solo'),
                        'clinic' => Tenant::planTierLabel('clinic'),
                    ]),
                SelectFilter::make('billing_status')
                    ->label(__('Billing'))
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'past_due' => 'Past due',
                        'suspended' => 'Suspended',
                        'read_only' => 'Read only',
                    ]),
                SelectFilter::make('marketer_id')
                    ->label(__('Marketer'))
                    ->relationship('marketer', 'display_name'),
            ])
            ->recordActions([
                // Two labelled buttons made the actions column ~290px wide, which is what
                // pushed Edit off the right edge on both desktop and phone.
                ActionGroup::make([
                    EditAction::make(),
                    TenantBackupActions::downloadAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
