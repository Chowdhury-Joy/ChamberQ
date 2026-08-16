<?php

namespace App\Filament\TenantAdmin\Resources\PayrollPayments\Pages;

use App\Filament\TenantAdmin\Resources\PayrollPayments\PayrollPaymentResource;
use App\Models\Employee;
use App\Services\HrPayrollService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPayments extends ListRecords
{
    protected static string $resource = PayrollPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
