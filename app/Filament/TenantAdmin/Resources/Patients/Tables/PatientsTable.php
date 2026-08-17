<?php

namespace App\Filament\TenantAdmin\Resources\Patients\Tables;

use App\Models\Booking;
use App\Models\Patient;
use App\Services\PatientService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nid')
                    ->label(__('NID'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('bookings_count')
                    ->counts('bookings')
                    ->label(__('Visits'))
                    ->sortable(),
                TextColumn::make('seen_before_software')
                    ->label(__('Paper file'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state
                        ? __('Before ChamberQ')
                        : '—')
                    ->color(fn (mixed $state): string => $state ? 'info' : 'gray'),
                TextColumn::make('display_age')
                    ->label(__('Age'))
                    ->state(fn (Patient $record): string => (string) ($record->displayAge() ?? '—')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('mergePatients')
                    ->label(__('Join two records'))
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->schema([
                        Select::make('keep_id')
                            ->label(__('Keep this record'))
                            ->options(fn () => Patient::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('remove_id')
                            ->label(__('Merge this record into the one above'))
                            ->options(fn () => Patient::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->different('keep_id'),
                    ])
                    ->action(function (array $data, PatientService $patientService): void {
                        $keep = Patient::findOrFail($data['keep_id']);
                        $remove = Patient::findOrFail($data['remove_id']);
                        $patientService->mergePatients($keep, $remove);

                        Notification::make()
                            ->title(__('Patient records joined'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('moveVisit')
                    ->label(__('Move a visit'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->schema([
                        Select::make('booking_id')
                            ->label(__('Visit to move'))
                            ->options(fn (Patient $record) => $record->bookings()
                                ->orderByDesc('booking_date')
                                ->get()
                                ->mapWithKeys(fn (Booking $booking) => [
                                    $booking->id => sprintf(
                                        '%s — %s (serial %d)',
                                        $booking->booking_date?->format('j M Y'),
                                        $booking->patient_name,
                                        $booking->serial_number,
                                    ),
                                ]))
                            ->searchable()
                            ->required(),
                        Select::make('target_patient_id')
                            ->label(__('Move to patient'))
                            ->options(fn (Patient $record) => Patient::query()
                                ->whereKeyNot($record->id)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Patient $record, array $data, PatientService $patientService): void {
                        $booking = Booking::findOrFail($data['booking_id']);
                        $target = Patient::findOrFail($data['target_patient_id']);

                        if ($booking->patient_id !== $record->id) {
                            Notification::make()
                                ->title(__('That visit does not belong to this patient.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $patientService->moveBookingToPatient($booking, $target);

                        Notification::make()
                            ->title(__('Visit moved'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
