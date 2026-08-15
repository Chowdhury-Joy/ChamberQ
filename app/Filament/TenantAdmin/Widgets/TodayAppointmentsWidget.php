<?php

namespace App\Filament\TenantAdmin\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class TodayAppointmentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = "Today's Scheduled Appointments";

    protected function getTableHeading(): string | Htmlable | null
    {
        return __("Today's Scheduled Appointments");
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('booking_date', Carbon::today()->toDateString())
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label(__('Serial #'))
                    ->badge()
                    ->color('sky')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient_name')
                    ->label(__('Patient Name'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('patient_phone')
                    ->label(__('Phone Number'))
                    ->icon('heroicon-m-phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bookable.doctor.name')
                    ->label(__('Doctor'))
                    ->default(fn ($record) => $record->bookable?->doctor?->name ?? __('Clinic Service'))
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('bookable.chamber.name')
                    ->label(__('Chamber'))
                    ->default(fn ($record) => $record->bookable?->chamber?->name ?? __('Main Chamber'))
                    ->icon('heroicon-m-map-pin'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'waiting' => __('Waiting'),
                        'called' => __('Called'),
                        'in_chamber' => __('In chamber'),
                        'completed' => __('Completed'),
                        'cancelled' => __('Cancelled'),
                        'no_show' => __('No-show'),
                        'skipped' => __('Skipped'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'called' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        'no_show' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading(__('No Appointments Scheduled Today'))
            ->emptyStateDescription(__('New patient bookings for today will appear here in real-time.'))
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
