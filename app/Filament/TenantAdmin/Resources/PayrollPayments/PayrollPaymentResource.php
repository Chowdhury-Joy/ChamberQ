<?php

namespace App\Filament\TenantAdmin\Resources\PayrollPayments;

use App\Filament\TenantAdmin\Resources\PayrollPayments\Pages\CreatePayrollPayment;
use App\Filament\TenantAdmin\Resources\PayrollPayments\Pages\ListPayrollPayments;
use App\Models\ChamberCashEntry;
use App\Models\Employee;
use App\Models\PayrollPayment;
use App\Services\HrPayrollService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollPaymentResource extends Resource
{
    protected static ?string $model = PayrollPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyBangladeshi;

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?string $navigationLabel = 'Payroll';

    protected static ?int $navigationSort = 4;

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

    public static function canEdit($record): bool
    {
        return false;
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
                ->native(false)
                ->live(),
            Forms\Components\TextInput::make('pay_period')
                ->label(__('Month (YYYY-MM)'))
                ->required()
                ->placeholder(now()->format('Y-m'))
                ->default(now()->format('Y-m')),
            Forms\Components\TextInput::make('amount_taka')
                ->label(__('Amount (৳)'))
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(fn (Get $get): int => (int) (Employee::find($get('employee_id'))?->monthly_salary_taka ?? 0)),
            Forms\Components\DatePicker::make('paid_on')
                ->label(__('Paid on'))
                ->required()
                ->default(now()),
            Forms\Components\Select::make('method')
                ->label(__('Paid via'))
                ->options(ChamberCashEntry::paymentMethods())
                ->required()
                ->native(false)
                ->default(ChamberCashEntry::METHOD_CASH),
            Forms\Components\Textarea::make('note')
                ->label(__('Note'))
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pay_period')->label(__('Month'))->sortable(),
                TextColumn::make('employee.name')->label(__('Employee'))->searchable(),
                TextColumn::make('amount_taka')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => '৳'.number_format($state)),
                TextColumn::make('paid_on')->date()->label(__('Paid on')),
                TextColumn::make('method')
                    ->formatStateUsing(fn (string $state): string => ChamberCashEntry::paymentMethods()[$state] ?? $state),
            ])
            ->defaultSort('pay_period', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPayments::route('/'),
            'create' => CreatePayrollPayment::route('/create'),
        ];
    }
}
