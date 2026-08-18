<?php

namespace App\Filament\TenantAdmin\Resources\ReferralCommissions;

use App\Filament\TenantAdmin\Resources\ReferralCommissions\Pages\ListReferralCommissions;
use App\Models\ChamberCashEntry;
use App\Models\ReferralCommission;
use App\Services\ReferralCommissionService;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReferralCommissionResource extends Resource
{
    protected static ?string $model = ReferralCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Referral ledger';

    protected static ?string $modelLabel = 'Referral commission';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() ?? false)
            && (tenant()?->hasReferrals() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_on')
                    ->label(__('Date'))
                    ->date(),
                TextColumn::make('referringDoctor.name')
                    ->label(__('Referring doctor'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('booking.patient_name')
                    ->label(__('Patient'))
                    ->searchable(),
                TextColumn::make('kind')
                    ->label(__('Type'))
                    ->formatStateUsing(fn (string $state): string => ReferralCommission::kindOptions()[$state] ?? $state),
                TextColumn::make('amount_taka')
                    ->label(__('Owed'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReferralCommission::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ReferralCommission::STATUS_PAID => 'success',
                        ReferralCommission::STATUS_VOID => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ReferralCommission::statusOptions()),
                SelectFilter::make('referring_doctor_id')
                    ->label(__('Referring doctor'))
                    ->relationship('referringDoctor', 'name'),
            ])
            ->toolbarActions([
                BulkAction::make('markPaid')
                    ->label(__('Mark selected as paid'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->form([
                        Select::make('method')
                            ->label(__('Paid via'))
                            ->options(ChamberCashEntry::paymentMethods())
                            ->required()
                            ->native(false),
                        Textarea::make('note')
                            ->label(__('Note')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        app(ReferralCommissionService::class)->markPaid(
                            $records,
                            auth()->user(),
                            (string) $data['method'],
                            filled($data['note'] ?? null) ? (string) $data['note'] : null,
                        );

                        Notification::make()
                            ->title(__('Referral payout recorded in cashbook'))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralCommissions::route('/'),
        ];
    }
}
