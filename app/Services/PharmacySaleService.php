<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\PharmacyDelivery;
use App\Models\PharmacyItem;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Support\PrescriptionQuantity;
use App\Support\StaffDeskScope;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PharmacySaleService
{
    /**
     * @param  list<array{pharmacy_item_id: int, qty: int, prescription_item_id?: ?string}>  $lines
     */
    public function sell(
        User $user,
        array $lines,
        string $method,
        bool $waived = false,
        ?Prescription $prescription = null,
        ?string $patientName = null,
        ?string $patientPhone = null,
        ?string $note = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
        int $discountTaka = 0,
        ?CarbonInterface $occurredOn = null,
    ): PharmacySale {
        $this->assertModule();

        $patientName = filled($patientName) ? trim($patientName) : null;
        $patientPhone = filled($patientPhone) ? trim($patientPhone) : null;
        if ($patientName === '') {
            $patientName = null;
        }
        if ($patientPhone === '') {
            $patientPhone = null;
        }

        if ($lines === []) {
            throw new InvalidArgumentException(__('Add at least one medicine.'));
        }

        $occurredOn ??= now(OperationalReportService::TIMEZONE);

        $lastUnique = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use (
                    $user, $lines, $method, $waived, $prescription, $patientName, $patientPhone, $note, $cashTaka, $onlineTaka, $onlineMethod, $discountTaka, $occurredOn
                ): PharmacySale {
                    $allocations = $this->allocate($user, $lines);
                    $total = 0;
                    foreach ($allocations as $row) {
                        $total += $row['qty'] * $row['item']->sell_price_taka;
                    }

                    if (! $waived && $total < 1) {
                        throw new InvalidArgumentException(__('Sale total must be at least ৳1, or waived.'));
                    }

                    $discount = $waived ? 0 : max(0, $discountTaka);
                    if ($discount > $total) {
                        throw new InvalidArgumentException(__('Discount cannot be more than the basket.'));
                    }
                    $collected = $waived ? 0 : $total - $discount;
                    $lineDiscounts = $this->allocateDiscount($allocations, $discount);

                    return $this->commitSale(
                        $user,
                        $allocations,
                        $lineDiscounts,
                        $method,
                        $waived,
                        $prescription,
                        $patientName,
                        $patientPhone,
                        $note,
                        $cashTaka,
                        $onlineTaka,
                        $onlineMethod,
                        $collected,
                        $occurredOn,
                    );
                });
            } catch (UniqueConstraintViolationException $e) {
                $lastUnique = $e;
            }
        }

        throw $lastUnique ?? new RuntimeException('Could not assign a medicine voucher number. Try again.');
    }

    /**
     * @param  list<array{item: PharmacyItem, delivery: PharmacyDelivery, qty: int, prescription_item_id: ?string}>  $allocations
     * @param  list<int>  $lineDiscounts
     */
    private function commitSale(
        User $user,
        array $allocations,
        array $lineDiscounts,
        string $method,
        bool $waived,
        ?Prescription $prescription,
        ?string $patientName,
        ?string $patientPhone,
        ?string $note,
        ?int $cashTaka,
        ?int $onlineTaka,
        ?string $onlineMethod,
        int $collected,
        CarbonInterface $occurredOn,
    ): PharmacySale {
        $saleChamberId = null;
        foreach ($allocations as $row) {
            $saleChamberId ??= $row['item']->chamber_id;
        }

        $cash = null;
        $split = ['cash_taka' => null, 'online_taka' => null, 'online_method' => null];

        if (! $waived && $collected > 0) {
            $cashService = app(ChamberCashService::class);
            $split = $cashService->paymentSplit($method, $collected, $cashTaka, $onlineTaka, $onlineMethod);
            $cash = $cashService->recordLockedIncome(
                $user,
                $collected,
                ChamberCashEntry::CATEGORY_PHARMACY,
                $method,
                $occurredOn,
                $note,
                $cashTaka,
                $onlineTaka,
                $onlineMethod,
                $saleChamberId !== null ? (int) $saleChamberId : null,
            );
        }

        $patient = null;
        $booking = null;
        if ($prescription) {
            $prescription->loadMissing(['patient', 'visitRecord.booking']);
            $patient = $prescription->patient;
            $booking = $prescription->visitRecord?->booking;
        }

        // Lock every sale row, not only numbered ones — an empty numbered set
        // locks nothing, so two first sales of the day can both take #1.
        PharmacySale::query()->lockForUpdate()->get(['id']);

        $sale = PharmacySale::create([
            'patient_id' => $patient?->id,
            'booking_id' => $booking?->id,
            'prescription_id' => $prescription?->id,
            'cash_entry_id' => $cash?->id,
            'patient_name' => $patient?->name ?? $patientName,
            'patient_phone' => $patient?->phone ?? $patientPhone,
            'method' => $waived ? ChamberCashEntry::METHOD_CASH : $method,
            'amount' => $collected,
            'cash_taka' => $waived ? null : $split['cash_taka'],
            'mobile_taka' => $waived ? null : $split['online_taka'],
            'mobile_method' => $waived ? null : $split['online_method'],
            'waived' => $waived,
            'recorded_by' => $user->id,
            'occurred_on' => $occurredOn->toDateString(),
            'note' => $note,
            'receipt_number' => ((int) PharmacySale::query()->max('receipt_number')) + 1,
        ]);

        $stock = app(PharmacyStockService::class);

        foreach ($allocations as $index => $row) {
            /** @var PharmacyItem $item */
            $item = $row['item'];
            /** @var PharmacyDelivery $delivery */
            $delivery = $row['delivery'];
            $qty = $row['qty'];
            $share = $delivery->company_share_taka;
            $shopCut = max(0, ($qty * max(0, $item->sell_price_taka - $share)) - ($lineDiscounts[$index] ?? 0));

            $saleItem = PharmacySaleItem::create([
                'pharmacy_sale_id' => $sale->id,
                'pharmacy_item_id' => $item->id,
                'pharmacy_delivery_id' => $delivery->id,
                'prescription_item_id' => $row['prescription_item_id'],
                'name' => $item->name,
                'qty' => $qty,
                'sell_price_taka' => $item->sell_price_taka,
                'company_share_taka' => $share,
                'shop_cut_taka' => $shopCut,
                'line_total_taka' => $qty * $item->sell_price_taka,
            ]);

            $delivery->qty_on_hand -= $qty;
            $delivery->qty_sold += $qty;
            $delivery->save();
            $stock->recordAdjustment($item, PharmacyStockAdjustment::KIND_SALE, $user, $delivery, null);

            if (! $waived) {
                app(PharmacyDoctorCommissionService::class)->accrueForLine($sale, $saleItem, $prescription);
            }
        }

        return $sale->fresh('items');
    }

    public function void(PharmacySale $sale, User $user): PharmacySale
    {
        $this->assertModule();

        return DB::transaction(function () use ($sale, $user): PharmacySale {
            $locked = PharmacySale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new InvalidArgumentException(__('That sale is already returned.'));
            }

            $today = now(OperationalReportService::TIMEZONE)->toDateString();
            if ($locked->occurred_on?->toDateString() !== $today) {
                throw new InvalidArgumentException(__('Only today\'s sales can be returned.'));
            }

            $stock = app(PharmacyStockService::class);

            foreach ($locked->items()->lockForUpdate()->get() as $line) {
                $delivery = PharmacyDelivery::query()->whereKey($line->pharmacy_delivery_id)->lockForUpdate()->firstOrFail();
                $item = PharmacyItem::query()->whereKey($line->pharmacy_item_id)->lockForUpdate()->firstOrFail();
                $delivery->qty_on_hand += $line->qty;
                $delivery->qty_sold -= $line->qty;
                $delivery->save();
                $stock->recordAdjustment($item, PharmacyStockAdjustment::KIND_VOID_RESTORE, $user, $delivery, __('Return sale'));
            }

            app(PharmacyDoctorCommissionService::class)->voidForSale($locked);

            if (! $locked->waived && $locked->amount > 0) {
                $refundChamberId = $locked->items()->with('item')->first()?->item?->chamber_id;
                $refund = app(ChamberCashService::class)->recordLockedExpense(
                    $user,
                    $locked->amount,
                    ChamberCashEntry::CATEGORY_PHARMACY_REFUND,
                    $locked->method,
                    now(OperationalReportService::TIMEZONE),
                    __('Return pharmacy sale'),
                    $locked->cash_taka,
                    $locked->mobile_taka,
                    $locked->mobile_method,
                    $refundChamberId !== null ? (int) $refundChamberId : null,
                );
                $locked->refund_cash_entry_id = $refund->id;
            }

            $locked->voided_at = now();
            $locked->save();

            return $locked->fresh('items');
        });
    }

    /**
     * @return list<array{pharmacy_item_id: ?int, name: string, suggested_qty: ?int, qty_on_hand: int, sell_price_taka: int, matched: bool}>
     */
    public function suggestionsFromPrescription(Prescription $prescription): array
    {
        $rows = [];
        $prescription->loadMissing(['items', 'visitRecord.booking.bookable']);
        $bookable = $prescription->visitRecord?->booking?->bookable;
        $chamberId = $bookable instanceof ScheduleSession
            ? (int) $bookable->chamber_id
            : null;

        foreach ($prescription->items as $line) {
            $item = PharmacyItem::matchByBrand($line->medicine_name, $chamberId);
            $rows[] = [
                'pharmacy_item_id' => $item?->id,
                'prescription_item_id' => $line->id,
                'name' => $line->medicine_name,
                'suggested_qty' => PrescriptionQuantity::total($line->frequency, $line->duration),
                'qty_on_hand' => $item?->qty_on_hand ?? 0,
                'sell_price_taka' => $item?->sell_price_taka ?? 0,
                'matched' => $item !== null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{pharmacy_item_id: int, qty: int, prescription_item_id?: ?string}>  $lines
     * @return list<array{item: PharmacyItem, delivery: PharmacyDelivery, qty: int, prescription_item_id: ?string}>
     */
    private function allocate(User $user, array $lines): array
    {
        $allocations = [];
        $remainingOnHand = [];
        $saleChamberId = null;

        foreach ($lines as $line) {
            $qty = (int) ($line['qty'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $item = PharmacyItem::query()
                ->whereKey((int) $line['pharmacy_item_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new InvalidArgumentException(__('That shop item is not on the list.'));
            }

            if (! StaffDeskScope::pharmacyItemIsVisible($user, $item)) {
                throw new InvalidArgumentException(__('This login cannot change that cupboard.'));
            }

            if ($saleChamberId !== null && (int) $item->chamber_id !== $saleChamberId) {
                throw new InvalidArgumentException(__('One sale cannot mix two centres.'));
            }
            $saleChamberId = $item->chamber_id !== null ? (int) $item->chamber_id : $saleChamberId;

            $remaining = $qty;
            $deliveries = PharmacyDelivery::query()
                ->where('pharmacy_item_id', $item->id)
                ->where('qty_on_hand', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery) {
                if ($remaining < 1) {
                    break;
                }
                $available = $remainingOnHand[$delivery->id] ?? $delivery->qty_on_hand;
                if ($available < 1) {
                    continue;
                }
                $take = min($remaining, $available);
                $remainingOnHand[$delivery->id] = $available - $take;
                $allocations[] = [
                    'item' => $item,
                    'delivery' => $delivery,
                    'qty' => $take,
                    'prescription_item_id' => $line['prescription_item_id'] ?? null,
                ];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException(__('Not enough :name on the shelf.', ['name' => $item->name]));
            }
        }

        if ($allocations === []) {
            throw new InvalidArgumentException(__('Add at least one medicine.'));
        }

        return $allocations;
    }

    /**
     * @param  list<array{item: PharmacyItem, delivery: PharmacyDelivery, qty: int, prescription_item_id: ?string}>  $allocations
     * @return array<int, int>
     */
    private function allocateDiscount(array $allocations, int $discount): array
    {
        $shares = [];
        if ($discount < 1 || $allocations === []) {
            foreach ($allocations as $index => $_) {
                $shares[$index] = 0;
            }

            return $shares;
        }

        $catalogue = 0;
        $lineTotals = [];
        foreach ($allocations as $index => $row) {
            $lineTotals[$index] = $row['qty'] * $row['item']->sell_price_taka;
            $catalogue += $lineTotals[$index];
        }

        $used = 0;
        $last = array_key_last($allocations);
        foreach ($allocations as $index => $_) {
            if ($index === $last) {
                $shares[$index] = max(0, $discount - $used);
            } else {
                $share = $catalogue > 0 ? intdiv($discount * $lineTotals[$index], $catalogue) : 0;
                $shares[$index] = $share;
                $used += $share;
            }
        }

        return $shares;
    }

    private function assertModule(): void
    {
        if (! tenant()?->hasPharmacy()) {
            throw new InvalidArgumentException(__('Pharmacy is not enabled for this chamber.'));
        }
    }
}
