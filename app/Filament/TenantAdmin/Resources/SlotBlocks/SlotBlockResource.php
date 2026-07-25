<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks;

use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\CreateSlotBlock;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\EditSlotBlock;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\ListSlotBlocks;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Schemas\SlotBlockForm;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Tables\SlotBlocksTable;
use App\Models\SlotBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;

class SlotBlockResource extends Resource
{
    protected static ?string $model = SlotBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\DatePicker::make('date')
                ->required()
                ->live(),
            Forms\Components\Select::make('chamber_id')
                ->relationship('chamber', 'name')
                ->live(),
            Forms\Components\Select::make('doctor_id')
                ->relationship('doctor', 'name')
                ->live(),
            Forms\Components\TextInput::make('reason')
                ->maxLength(255),
            Forms\Components\Checkbox::make('confirm_cancellation')
                ->label('Conflict Detected: Confirm cancellation of active bookings')
                ->visible(function (\Filament\Schemas\Components\Utilities\Get $get) {
                    $date = $get('date');
                    if (!$date) return false;
                    
                    $query = \App\Models\Booking::where('booking_date', $date)->where('status', '!=', 'cancelled');
                    if ($get('chamber_id')) {
                        $query->where(function($q) use ($get) {
                            $q->whereHasMorph('bookable', [\App\Models\ScheduleSession::class], function($sub) use($get) {
                                $sub->where('chamber_id', $get('chamber_id'));
                            })->orWhereHasMorph('bookable', [\App\Models\LabCollectionSlot::class], function($sub) use($get) {
                                $sub->where('chamber_id', $get('chamber_id'));
                            });
                        });
                    }
                    if ($get('doctor_id')) {
                        $query->whereHasMorph('bookable', [\App\Models\ScheduleSession::class], function($q) use($get) {
                            $q->where('doctor_id', $get('doctor_id'));
                        });
                    }
                    return $query->exists();
                })
                ->accepted()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return SlotBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlotBlocks::route('/'),
            'create' => CreateSlotBlock::route('/create'),
            'edit' => EditSlotBlock::route('/{record}/edit'),
        ];
    }
}
