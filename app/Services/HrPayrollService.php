<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class HrPayrollService
{
    public function recordSalaryPayment(
        Employee $employee,
        User $user,
        string $payPeriod,
        int $amountTaka,
        string $method,
        ?CarbonInterface $paidOn = null,
        ?string $note = null,
        bool $postToCashbook = true,
    ): PayrollPayment {
        if (! preg_match('/^\d{4}-\d{2}$/', $payPeriod)) {
            throw new InvalidArgumentException(__('Pay period must be YYYY-MM.'));
        }

        if ($amountTaka < 1) {
            throw new InvalidArgumentException(__('Salary amount must be at least ৳1.'));
        }

        $this->assertMethod($method);

        $existing = PayrollPayment::query()
            ->where('employee_id', $employee->id)
            ->where('pay_period', $payPeriod)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException(__('Salary for :period is already recorded for this employee.', [
                'period' => $payPeriod,
            ]));
        }

        $paidOn ??= now(OperationalReportService::TIMEZONE);
        $cashEntry = null;

        if ($postToCashbook) {
            $cashEntry = ChamberCashEntry::create([
                'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
                'amount' => $amountTaka,
                'category' => ChamberCashEntry::CATEGORY_SALARY,
                'method' => $method,
                'recorded_by' => $user->id,
                'occurred_on' => $paidOn->toDateString(),
                'note' => __('Salary :period — :name', [
                    'period' => $payPeriod,
                    'name' => $employee->name,
                ]).(filled($note) ? ' — '.$note : ''),
            ]);
        }

        return PayrollPayment::create([
            'employee_id' => $employee->id,
            'pay_period' => $payPeriod,
            'amount_taka' => $amountTaka,
            'paid_on' => $paidOn->toDateString(),
            'method' => $method,
            'cash_entry_id' => $cashEntry?->id,
            'note' => $note,
            'recorded_by' => $user->id,
        ]);
    }

    public function reviewLeave(LeaveRequest $request, User $reviewer, bool $approve, ?string $reviewNote = null): LeaveRequest
    {
        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw new InvalidArgumentException(__('This leave request was already reviewed.'));
        }

        $request->update([
            'status' => $approve ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reviewNote,
        ]);

        return $request->refresh();
    }

    private function assertMethod(string $method): void
    {
        $allowed = array_keys(ChamberCashEntry::paymentMethods());

        if (! in_array($method, $allowed, true)) {
            throw new InvalidArgumentException(__('Pick a payment method.'));
        }
    }
}
