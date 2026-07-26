<?php

namespace App\Filament\TenantAdmin\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class TodayAppointmentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = "Today's Scheduled Appointments";

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->whereDate('booking_date', Carbon::today())
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial #')
                    ->badge()
                    ->color('sky')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient_name')
                    ->label('Patient Name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('patient_phone')
                    ->label('Phone Number')
                    ->icon('heroicon-m-phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bookable.doctor.name')
                    ->label('Doctor')
                    ->default(fn ($record) => $record->bookable?->doctor?->name ?? 'Clinic Service')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('bookable.chamber.name')
                    ->label('Chamber')
                    ->default(fn ($record) => $record->bookable?->chamber?->name ?? 'Main Chamber')
                    ->icon('heroicon-m-map-pin'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        default => 'warning',
                    }),
            ])
            ->emptyStateHeading('No Appointments Scheduled Today')
            ->emptyStateDescription('New patient bookings for today will appear here in real-time.')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
