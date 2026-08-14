<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTenantsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Registered Tenants';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()->latest()->limit(8)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Clinic / Practice Name')
                    ->weight('bold')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('id')
                    ->label('Platform path')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-link'),

                Tables\Columns\TextColumn::make('domains.domain')
                    ->label('Custom domains')
                    ->badge()
                    ->color('sky')
                    ->icon('heroicon-m-globe-alt')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('plan_tier')
                    ->label('Subscription Tier')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'clinic' => 'success',
                        'solo' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Tenant::planTierLabel($state)),

                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Contact Phone')
                    ->icon('heroicon-m-phone')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ]);
    }
}
