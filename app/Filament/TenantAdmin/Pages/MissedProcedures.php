<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\StationsHandoffForm;
use App\Models\Booking;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MissedProcedures extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Missed procedures';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Missed procedures';

    protected string $view = 'filament.tenant-admin.pages.missed-procedures';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) ($user?->canWorkDesk())
            && (tenant()?->hasStations() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(StationsHandoffService::class)->overdueProceduresQuery())
            ->columns([
                TextColumn::make('patient_name')
                    ->label(__('Patient'))
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label(__('Phone')),
                TextColumn::make('bookable.session_name')
                    ->label(__('Sitting'))
                    ->formatStateUsing(function (?string $state, Booking $record): string {
                        $session = $record->bookable;
                        $when = $session && filled($session->start_time)
                            ? \Carbon\Carbon::parse($session->start_time)->format('g:i A')
                                .' – '.\Carbon\Carbon::parse($session->end_time)->format('g:i A')
                            : '';

                        return trim(($state ?? __('Intervention')).($when !== '' ? ' · '.$when : ''));
                    }),
                TextColumn::make('booking_date')
                    ->label(__('Missed date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('days_overdue')
                    ->label(__('Days overdue'))
                    ->state(fn (Booking $record): int => (int) Carbon::parse($record->booking_date)->startOfDay()
                        ->diffInDays(Carbon::today())),
                TextColumn::make('relatedBooking.patient_name')
                    ->label(__('From visit'))
                    ->formatStateUsing(function (?string $state, Booking $record): string {
                        $visit = $record->relatedBooking;
                        if (! $visit) {
                            return '—';
                        }

                        $when = $visit->booking_date?->isoFormat('D MMM');

                        return trim($visit->patient_name.($when ? ' · '.$when : '').' · #'.$visit->serial_number);
                    }),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Booking $record): string => $record->missedProcedureWhatsappLink())
                    ->openUrlInNewTab()
                    ->visible(fn (Booking $record): bool => filled($record->patient_phone)),
                StationsHandoffForm::moveAction(
                    Action::make('moveIntervention'),
                ),
            ])
            ->emptyStateHeading(__('No missed procedures'))
            ->emptyStateDescription(__('Unfinished past-dated intervention rows appear here. Nothing is auto-cancelled.'));
    }
}
