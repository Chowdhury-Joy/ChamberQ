<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ChamberCashService
{
    public function suggestedAmountTaka(Booking $booking): int
    {
        $doctor = Doctor::resolveForBooking($booking);
        $consult = (int) ($doctor?->default_fee_taka ?? 0);
        $labs = (int) round((float) $booking->labTests()->sum('booking_lab_test.price_at_booking'));

        return $consult + $labs;
    }

    public function recordPatientIncome(
        Booking $booking,
        User $user,
        int $amount,
        string $method,
        bool $waived = false,
        ?string $note = null,
        ?CarbonInterface $occurredOn = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($waived) {
            if ($amount < 1) {
                $amount = $this->suggestedAmountTaka($booking);
            }
        } elseif ($amount < 1) {
            throw new InvalidArgumentException('Patient fee must be at least ৳1, or waived.');
        }

        $booking->loadMissing('bookable');
        $session = $booking->bookable_type === ScheduleSession::class
            ? $booking->bookable
            : null;

        $values = [
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $amount,
            'category' => $waived ? ChamberCashEntry::CATEGORY_WAIVED : ChamberCashEntry::CATEGORY_PATIENT,
            'method' => $method,
            'chamber_id' => $session?->chamber_id,
            'doctor_id' => $session?->doctor_id,
            'recorded_by' => $user->id,
            'occurred_on' => ($occurredOn ?? now(OperationalReportService::TIMEZONE))->toDateString(),
            'note' => $note,
        ];

        $existing = ChamberCashEntry::query()->where('booking_id', $booking->id)->first();

        if ($existing) {
            $existing->fill($values)->save();

            return $existing;
        }

        return ChamberCashEntry::create([
            ...$values,
            'booking_id' => $booking->id,
        ]);
    }

    public function recordExpense(
        User $user,
        int $amount,
        string $category,
        string $method,
        CarbonInterface $occurredOn,
        ?int $chamberId = null,
        ?string $note = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Expense must be at least ৳1.');
        }

        if (! array_key_exists($category, ChamberCashEntry::expenseCategories())) {
            throw new InvalidArgumentException('Unknown expense category.');
        }

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'amount' => $amount,
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
        string $method,
        CarbonInterface $occurredOn,
        ?int $chamberId = null,
        ?string $note = null,
    ): ChamberCashEntry {
        $this->assertMethod($method);

        if ($amount < 1) {
            throw new InvalidArgumentException('Income must be at least ৳1.');
        }

        return ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $amount,
            'category' => ChamberCashEntry::CATEGORY_OTHER_INCOME,
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

    private function assertMethod(string $method): void
    {
        if (! array_key_exists($method, ChamberCashEntry::methods())) {
            throw new InvalidArgumentException('Unknown payment method.');
        }
    }
}
