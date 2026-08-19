<?php

namespace App\Filament\TenantAdmin\Resources\PharmacyDoctorCommissions;

use App\Filament\TenantAdmin\Resources\PharmacyDoctorCommissions\Pages\ListPharmacyDoctorCommissions;
use App\Models\ChamberCashEntry;
use App\Models\PharmacyDoctorCommission;
use App\Services\PharmacyDoctorCommissionService;
use App\Support\PharmacyAccess;
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
use InvalidArgumentException;

class PharmacyDoctorCommissionResource extends Resource
{
    protected static ?string $model = PharmacyDoctorCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Doctor pharmacy cuts';

    protected static ?string $modelLabel = 'Doctor pharmacy cut';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return PharmacyAccess::moduleOn()
            && ($user?->isAdmin() ?? false);
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
                TextColumn::make('occurred_on')->label(__('Date'))->date(),
                TextColumn::make('doctor.name')->label(__('Doctor'))->searchable(),
                TextColumn::make('amount_taka')
                    ->label(__('Owed'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('percent')->label(__('%')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PharmacyDoctorCommission::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        PharmacyDoctorCommission::STATUS_PAID => 'success',
                        PharmacyDoctorCommission::STATUS_VOID => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->filters([
                SelectFilter::make('status')->options(PharmacyDoctorCommission::statusOptions()),
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
                        Textarea::make('note')->label(__('Note')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        try {
                            app(PharmacyDoctorCommissionService::class)->markPaid(
                                $records,
                                auth()->user(),
                                (string) $data['method'],
                                filled($data['note'] ?? null) ? (string) $data['note'] : null,
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Doctor pharmacy payout recorded in cashbook'))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacyDoctorCommissions::route('/'),
        ];
    }
}
