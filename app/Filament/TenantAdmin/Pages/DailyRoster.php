<?php

namespace App\Filament\TenantAdmin\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use App\Models\Booking;
use Carbon\Carbon;
use App\Services\BookingService;
use App\Models\ScheduleSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DailyRoster extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.tenant-admin.pages.daily-roster';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('booking_date', Carbon::today())
                    ->orderByRaw("FIELD(status, 'in_chamber', 'waiting', 'completed', 'cancelled')")
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')->label('Serial'),
                TextColumn::make('patient_name')->label('Name')->searchable(),
                TextColumn::make('patient_phone')->label('Phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'primary',
                    }),
            ])
            ->actions([
                Action::make('call')
                    ->label('Call to Chamber')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => $record->status === 'waiting')
                    ->action(fn (Booking $record) => $record->update(['status' => 'in_chamber'])),

                Action::make('complete')
                    ->label('Mark Completed')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => in_array($record->status, ['waiting', 'in_chamber']))
                    ->action(fn (Booking $record) => $record->update(['status' => 'completed'])),
            ])
            ->headerActions([
                Action::make('newWalkIn')
                    ->label('New Walk-In')
                    ->form([
                        TextInput::make('patient_name')->required(),
                        TextInput::make('patient_phone')->required(),
                        Select::make('session_id')
                            ->label('Session')
                            ->options(function () {
                                return ScheduleSession::where('day_of_week', Carbon::today()->dayOfWeek)->pluck('session_name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, BookingService $bookingService) {
                        $session = ScheduleSession::find($data['session_id']);
                        $bookingService->createBookingForSession(
                            $session,
                            Carbon::today()->toDateString(),
                            $data['patient_name'],
                            $data['patient_phone']
                        );
                    })
            ]);
    }
}
