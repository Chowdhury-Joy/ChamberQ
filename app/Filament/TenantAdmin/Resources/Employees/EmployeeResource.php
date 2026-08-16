<?php

namespace App\Filament\TenantAdmin\Resources\Employees;

use App\Filament\TenantAdmin\Resources\Employees\Pages\CreateEmployee;
use App\Filament\TenantAdmin\Resources\Employees\Pages\EditEmployee;
use App\Filament\TenantAdmin\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?string $navigationLabel = 'Employees';

    protected static ?int $navigationSort = 1;

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
            Forms\Components\Select::make('user_id')
                ->label(__('Linked login (optional)'))
                ->options(fn (): array => User::query()
                    ->whereIn('role', [User::ROLE_STAFF, User::ROLE_DOCTOR, User::ROLE_ADMIN])
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->native(false),
            Forms\Components\TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(20),
            Forms\Components\TextInput::make('job_title')
                ->label(__('Job title'))
                ->maxLength(255),
            Forms\Components\TextInput::make('monthly_salary_taka')
                ->label(__('Monthly salary (৳)'))
                ->numeric()
                ->minValue(0)
                ->default(0),
            Forms\Components\DatePicker::make('joined_on')
                ->label(__('Joined on')),
            Forms\Components\Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(3),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('job_title')->label(__('Role')),
                TextColumn::make('phone'),
                TextColumn::make('monthly_salary_taka')
                    ->label(__('Salary'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
