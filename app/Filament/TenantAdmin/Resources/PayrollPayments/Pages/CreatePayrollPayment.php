<?php

namespace App\Filament\TenantAdmin\Resources\PayrollPayments\Pages;

use App\Filament\TenantAdmin\Resources\PayrollPayments\PayrollPaymentResource;
use App\Models\Employee;
use App\Services\HrPayrollService;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPayment extends CreateRecord
{
    protected static string $resource = PayrollPaymentResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(HrPayrollService::class)->recordSalaryPayment(
            Employee::findOrFail($data['employee_id']),
            auth()->user(),
            (string) $data['pay_period'],
            (int) $data['amount_taka'],
            (string) $data['method'],
            isset($data['paid_on']) ? \Carbon\Carbon::parse($data['paid_on']) : null,
            filled($data['note'] ?? null) ? (string) $data['note'] : null,
        );
    }
}
