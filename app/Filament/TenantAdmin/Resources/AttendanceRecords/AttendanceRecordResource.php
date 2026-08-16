<?php

namespace App\Filament\TenantAdmin\Resources\AttendanceRecords;

use App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages\CreateAttendanceRecord;
use App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages\EditAttendanceRecord;
use App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() ?? false)
            && (tenant()?->hasHr() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('employee_id')
                ->label(__('Employee'))
                ->options(fn (): array => Employee::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->native(false),
            Forms\Components\DatePicker::make('work_date')
                ->label(__('Date'))
                ->required()
                ->default(now()),
            Forms\Components\Select::make('status')
                ->label(__('Status'))
                ->options(AttendanceRecord::statusOptions())
                ->required()
                ->native(false)
                ->default(AttendanceRecord::STATUS_PRESENT),
            Forms\Components\TimePicker::make('check_in_at')
                ->label(__('Check in')),
            Forms\Components\TimePicker::make('check_out_at')
                ->label(__('Check out')),
            Forms\Components\Textarea::make('note')
                ->label(__('Note'))
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('employee.name')->label(__('Employee'))->searchable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (string $state): string => AttendanceRecord::statusOptions()[$state] ?? $state),
                TextColumn::make('check_in_at')->label(__('In')),
                TextColumn::make('check_out_at')->label(__('Out')),
            ])
            ->defaultSort('work_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceRecords::route('/'),
            'create' => CreateAttendanceRecord::route('/create'),
            'edit' => EditAttendanceRecord::route('/{record}/edit'),
        ];
    }
}
