<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks;

use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\CreateSlotBlock;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\EditSlotBlock;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\ListSlotBlocks;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Schemas\SlotBlockForm;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Tables\SlotBlocksTable;
use App\Models\SlotBlock;
use App\Services\SlotBlockService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;

class SlotBlockResource extends Resource
{
    protected static ?string $model = SlotBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function canViewAny(): bool
    {
        return (auth()->user()?->canManageOps() ?? false)
            && (tenant()?->hasFrontDoor() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

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
                ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => __(
                    'I understand :count existing booking(s) will be cancelled. I will notify these patients.',
                    ['count' => static::affectedCount($get)]
                ))
                ->helperText(__('After saving you will get a list of WhatsApp links, one per patient.'))
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => static::affectedCount($get) > 0)
                ->accepted()
                ->dehydrated(false),
        ]);
    }

    /**
     * Counts what saving this block would cancel, using the same query the
     * service uses when it actually cancels — so the warning can never disagree
     * with what happens.
     */
    private static function affectedCount(\Filament\Schemas\Components\Utilities\Get $get): int
    {
        if (blank($get('date'))) {
            return 0;
        }

        $draft = new SlotBlock([
            'date' => $get('date'),
            'chamber_id' => $get('chamber_id'),
            'doctor_id' => $get('doctor_id'),
        ]);

        return app(SlotBlockService::class)->affectedCount($draft);
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
