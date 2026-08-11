<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions;

use App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages\CreateScheduleSession;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages\EditScheduleSession;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages\ListScheduleSessions;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\Schemas\ScheduleSessionForm;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\Tables\ScheduleSessionsTable;
use App\Models\ScheduleSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScheduleSessionResource extends Resource
{
    protected static ?string $model = ScheduleSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageOps() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ScheduleSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduleSessionsTable::configure($table);
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
            'index' => ListScheduleSessions::route('/'),
            'create' => CreateScheduleSession::route('/create'),
            'edit' => EditScheduleSession::route('/{record}/edit'),
        ];
    }
}
