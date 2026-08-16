<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WaitingForEarlierDate extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Waiting for earlier date';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Waiting for earlier date';

    protected string $view = 'filament.tenant-admin.pages.waiting-for-earlier-date';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) ($user?->canWorkDesk());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('wants_earlier_date', true)
                    ->where('booking_date', '>=', now()->toDateString())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('booking_date')
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')
                    ->label(__('Serial'))
                    ->sortable(),
                TextColumn::make('patient_name')
                    ->label(__('Patient'))
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label(__('Phone')),
                TextColumn::make('booking_date')
                    ->label(__('Booked for'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Booking $record): string => $record->earlierDateWhatsappLink())
                    ->openUrlInNewTab()
                    ->visible(fn (Booking $record): bool => filled($record->patient_phone)),
            ])
            ->emptyStateHeading(__('No patients waiting for an earlier date'))
            ->emptyStateDescription(__('Patients can opt in during online booking when all soon dates are full.'));
    }
}
