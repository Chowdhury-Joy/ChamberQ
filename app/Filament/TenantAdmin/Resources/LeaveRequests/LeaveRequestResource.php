<?php

namespace App\Filament\TenantAdmin\Resources\LeaveRequests;

use App\Filament\TenantAdmin\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\TenantAdmin\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\TenantAdmin\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\HrPayrollService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?string $navigationLabel = 'Leave';

    protected static ?int $navigationSort = 3;

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
            Forms\Components\DatePicker::make('start_date')
                ->label(__('From'))
                ->required(),
            Forms\Components\DatePicker::make('end_date')
                ->label(__('To'))
                ->required(),
            Forms\Components\Select::make('leave_type')
                ->label(__('Type'))
                ->options(LeaveRequest::typeOptions())
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('reason')
                ->label(__('Reason'))
                ->rows(3),
            Forms\Components\Textarea::make('review_note')
                ->label(__('Review note'))
                ->rows(2)
                ->visible(fn (?LeaveRequest $record): bool => $record?->status !== LeaveRequest::STATUS_PENDING),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label(__('Employee'))->searchable(),
                TextColumn::make('start_date')->date()->label(__('From')),
                TextColumn::make('end_date')->date()->label(__('To')),
                TextColumn::make('leave_type')
                    ->formatStateUsing(fn (string $state): string => LeaveRequest::typeOptions()[$state] ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeaveRequest::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        LeaveRequest::STATUS_APPROVED => 'success',
                        LeaveRequest::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveRequest::statusOptions()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequest::STATUS_PENDING)
                    ->action(function (LeaveRequest $record): void {
                        app(HrPayrollService::class)->reviewLeave($record, auth()->user(), true);
                        Notification::make()->title(__('Leave approved'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('review_note')
                            ->label(__('Reason')),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        app(HrPayrollService::class)->reviewLeave(
                            $record,
                            auth()->user(),
                            false,
                            filled($data['review_note'] ?? null) ? (string) $data['review_note'] : null,
                        );
                        Notification::make()->title(__('Leave rejected'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
