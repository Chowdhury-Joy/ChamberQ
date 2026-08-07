<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Tables;

use App\Models\SlotBlock;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class SlotBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                // Resolved names, never raw foreign-key ids.
                TextColumn::make('chamber.name')
                    ->label(__('Chamber'))
                    ->placeholder(__('All chambers'))
                    ->searchable(),
                TextColumn::make('doctor.name')
                    ->label(__('Doctor'))
                    ->placeholder(__('All doctors'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('cancelled_bookings_count')
                    ->label(__('Cancelled'))
                    ->counts('cancelledBookings')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                Action::make('notify')
                    ->label(__('Notify patients'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->visible(fn (SlotBlock $record) => $record->cancelledBookings()->exists())
                    ->modalHeading(__('Patients to notify'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (SlotBlock $record): View => view(
                        'filament.tenant-admin.slot-block-notify',
                        [
                            'bookings' => $record->cancelledBookings()->orderBy('serial_number')->get(),
                            'stage' => \App\Models\Doctor::NOTIFY_CANCELLATION,
                        ]
                    )),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
