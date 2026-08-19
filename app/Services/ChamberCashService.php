<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class ChamberCashService
{
    public function suggestedAmountTaka(Booking $booking): int
    {
        return $this->amountForFeeType($booking, Doctor::FEE_CONSULTATION);
    }

    /**
     * @return array<string, string>
     */
    public function feeTypeOptions(Booking $booking): array
    {
        $doctor = Doctor::resolveForBooking($booking);
        $options = [];

        foreach ($doctor?->feeTypes() ?? [] as $key => $row) {
            $label = $key === Doctor::FEE_CONSULTATION ? __('Consultation') : $row['label'];
            $options[$key] = $label.' — ৳'.number_format($row['amount']);
        }

        return $options;
    }

    public function amountForFeeType(Booking $booking, string $feeType): int
    {
        $doctor = Doctor::resolveForBooking($booking);
        $types = $doctor?->feeTypes() ?? [];

        if (! isset($types[$feeType])) {
            throw new InvalidArgumentException('That fee is not on this doctor\'s list.');
        }

        $labs = (int) round((float) $booking->labTests()->sum('booking_lab_test.price_at_booking'));

        return $types[$feeType]['amount'] + $labs;
    }

    public function recordPatientIncome(
        Booking $booking,
        User $user,
        string $method,
        bool $waived = false,
        ?string $note = null,
        ?CarbonInterface $occurredOn = null,
        string $feeType = Doctor::FEE_CONSULTATION,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        $amount = $this->amountForFeeType($booking, $feeType);

        if (! $waived && $amount < 1) {
            throw new InvalidArgumentException('Patient fee must be at least ৳1, or waived.');
        }

        $booking->loadMissing('bookable');
        $session = $booking->bookable_type === ScheduleSession::class
            ? $booking->bookable
            : null;

        $split = $waived
            ? ['cash_taka' => null, 'online_taka' => null, 'online_method' => null]
            : $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);

        $values = [
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $amount,
            'fee_type' => $feeType,
            'cash_taka' => $split['cash_taka'],
            'mobile_taka' => $split['online_taka'],
            'mobile_method' => $split['online_method'],
            'category' => $waived ? ChamberCashEntry::CATEGORY_WAIVED : ChamberCashEntry::CATEGORY_PATIENT,
            'method' => $method,
            'chamber_id' => $session?->chamber_id,
            'doctor_id' => $session?->doctor_id,
            'recorded_by' => $user->id,
            'occurred_on' => ($occurredOn ?? now(OperationalReportService::TIMEZONE))->toDateString(),
            'note' => $note,
        ];

        $existing = ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('direction', ChamberCashEntry::DIRECTION_INCOME)
            ->first();

        if ($existing) {
            $existing->fill($values)->save();
            if (! $waived && $amount > 0) {
                app(PatientFeeRefundService::class)->discardRefundOnRecollect($booking);
            }

            return $existing;
        }

        try {
            $entry = ChamberCashEntry::create([
                ...$values,
                'booking_id' => $booking->id,
            ]);
            if (! $waived && $amount > 0) {
                app(PatientFeeRefundService::class)->discardRefundOnRecollect($booking);
            }

            return $entry;
        } catch (UniqueConstraintViolationException) {
            $race = ChamberCashEntry::query()
                ->where('booking_id', $booking->id)
                ->where('direction', ChamberCashEntry::DIRECTION_INCOME)
                ->firstOrFail();
            $race->fill($values)->save();
            if (! $waived && $amount > 0) {
                app(PatientFeeRefundService::class)->discardRefundOnRecollect($booking);
            }

            return $race;
        }
    }

    public function recordExpense(
        User $user,
        int $amount,
        string $category,
        string $method,
        CarbonInterface $occurredOn,
        ?int $chamberId = null,
        ?string $note = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Expense must be at least ৳1.');
        }

        app(CashCategoryService::class)->validateManualExpenseCategory($category);

        $split = $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'amount' => $amount,
            'cash_taka' => $split['cash_taka'],
            'mobile_taka' => $split['online_taka'],
            'mobile_method' => $split['online_method'],
            'category' => $category,
            'method' => $method,
            'chamber_id' => $chamberId,
            'recorded_by' => $user->id,
            'occurred_on' => $occurredOn->toDateString(),
            'note' => $note,
        ]);
    }

    public function recordOtherIncome(
        User $user,
        int $amount,
        string $category,
        string $method,
        CarbonInterface $occurredOn,
        ?int $chamberId = null,
        ?string $note = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Income must be at least ৳1.');
        }

        app(CashCategoryService::class)->validateManualIncomeCategory($category);

        $split = $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $amount,
            'cash_taka' => $split['cash_taka'],
            'mobile_taka' => $split['online_taka'],
            'mobile_method' => $split['online_method'],
            'category' => $category,
            'method' => $method,
            'chamber_id' => $chamberId,
            'recorded_by' => $user->id,
            'occurred_on' => $occurredOn->toDateString(),
            'note' => $note,
        ]);
    }

    /**
     * @return array{income: int, expense: int, net: int, waived_count: int, waived_amount: int}
     */
    public function summaryForRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDay = $from->toDateString();
        $toDay = $to->toDateString();

        $rows = ChamberCashEntry::query()
            ->where('occurred_on', '>=', $fromDay)
            ->where('occurred_on', '<=', $toDay)
            ->get(['direction', 'category', 'amount']);

        $income = 0;
        $expense = 0;
        $waivedCount = 0;
        $waivedAmount = 0;

        foreach ($rows as $row) {
            if ($row->direction === ChamberCashEntry::DIRECTION_EXPENSE) {
                $expense += $row->amount;

                continue;
            }

            if ($row->category === ChamberCashEntry::CATEGORY_WAIVED) {
                $waivedCount++;
                $waivedAmount += $row->amount;

                continue;
            }

            $income += $row->amount;
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'waived_count' => $waivedCount,
            'waived_amount' => $waivedAmount,
        ];
    }

    /**
     * @return array{cash_taka: ?int, online_taka: ?int, online_method: ?string}
     */
    public function paymentSplit(
        string $method,
        int $amount,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): array {
        return $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);
    }

    public function recordLockedIncome(
        User $user,
        int $amount,
        string $category,
        string $method,
        CarbonInterface $occurredOn,
        ?string $note = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Income must be at least ৳1.');
        }

        $split = $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $amount,
            'cash_taka' => $split['cash_taka'],
            'mobile_taka' => $split['online_taka'],
            'mobile_method' => $split['online_method'],
            'category' => $category,
            'method' => $method,
            'recorded_by' => $user->id,
            'occurred_on' => $occurredOn->toDateString(),
            'note' => $note,
        ]);
    }

    public function recordLockedExpense(
        User $user,
        int $amount,
        string $category,
        string $method,
        CarbonInterface $occurredOn,
        ?string $note = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Expense must be at least ৳1.');
        }

        $split = $this->resolvePaymentSplit($method, $amount, $cashTaka, $onlineTaka, $onlineMethod);

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'amount' => $amount,
            'cash_taka' => $split['cash_taka'],
            'mobile_taka' => $split['online_taka'],
            'mobile_method' => $split['online_method'],
            'category' => $category,
            'method' => $method,
            'recorded_by' => $user->id,
            'occurred_on' => $occurredOn->toDateString(),
            'note' => $note,
        ]);
    }

    private function assertMethod(string $method): void
    {
        if (! array_key_exists($method, ChamberCashEntry::methods())) {
            throw new InvalidArgumentException('Unknown payment method.');
        }
    }

    /**
     * @return array{cash_taka: ?int, online_taka: ?int, online_method: ?string}
     */
    private function resolvePaymentSplit(
        string $method,
        int $amount,
        ?int $cashTaka,
        ?int $onlineTaka,
        ?string $onlineMethod,
    ): array {
        if ($method === ChamberCashEntry::METHOD_MIXED) {
            $cash = max(0, (int) $cashTaka);
            $online = max(0, (int) $onlineTaka);

            if ($cash + $online !== $amount) {
                throw new InvalidArgumentException(__('Cash plus online must equal the total amount.'));
            }

            if ($online > 0 && ! array_key_exists((string) $onlineMethod, ChamberCashEntry::onlineMethods())) {
                throw new InvalidArgumentException(__('Pick how the online part was paid.'));
            }

            return [
                'cash_taka' => $cash,
                'online_taka' => $online > 0 ? $online : null,
                'online_method' => $online > 0 ? $onlineMethod : null,
            ];
        }

        if (array_key_exists($method, ChamberCashEntry::onlineMethods())) {
            return [
                'cash_taka' => null,
                'online_taka' => $amount,
                'online_method' => $method,
            ];
        }

        return [
            'cash_taka' => $method === ChamberCashEntry::METHOD_CASH ? $amount : null,
            'online_taka' => null,
            'online_method' => null,
        ];
    }
}
