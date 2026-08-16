<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides;

use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages\CreateScheduleSessionOverride;
use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages\EditScheduleSessionOverride;
use App\Filament\TenantAdmin\Resources\ScheduleSessionOverrides\Pages\ListScheduleSessionOverrides;
use App\Models\ScheduleSessionOverride;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleSessionOverrideResource extends Resource
{
    protected static ?string $model = ScheduleSessionOverride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Sitting day overrides';

    protected static ?string $modelLabel = 'Day override';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->canManageOps() ?? false)
            && (tenant()?->hasStations() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('schedule_session_id')
                ->label(__('Sitting'))
                ->relationship('scheduleSession', 'session_name')
                ->getOptionLabelFromRecordUsing(fn ($record) => sprintf(
                    '%s — %s (%s–%s)',
                    $record->doctor?->name ?? '?',
                    $record->session_name,
                    $record->start_time,
                    $record->end_time,
                ))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\DatePicker::make('override_date')
                ->label(__('Date'))
                ->required(),
            Forms\Components\TimePicker::make('start_time')
                ->seconds(false),
            Forms\Components\TimePicker::make('end_time')
                ->seconds(false),
            Forms\Components\TextInput::make('slot_cap')
                ->label(__('Seat cap'))
                ->numeric()
                ->minValue(1),
            Forms\Components\TextInput::make('walk_in_overflow_cap')
                ->label(__('Extra walk-in seats'))
                ->numeric()
                ->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('override_date')->date()->sortable(),
                TextColumn::make('scheduleSession.session_name')->label(__('Sitting')),
                TextColumn::make('start_time')->time(),
                TextColumn::make('end_time')->time(),
                TextColumn::make('slot_cap')->label(__('Cap')),
            ])
            ->defaultSort('override_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleSessionOverrides::route('/'),
            'create' => CreateScheduleSessionOverride::route('/create'),
            'edit' => EditScheduleSessionOverride::route('/{record}/edit'),
        ];
    }
}
