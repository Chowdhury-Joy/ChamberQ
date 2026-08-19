<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\PharmacyDelivery;
use App\Models\PharmacyDoctorCommission;
use App\Models\PharmacyItem;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Models\Prescription;
use App\Models\User;
use App\Support\PrescriptionQuantity;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        ?CarbonInterface $occurredOn = null,
    ): PharmacySale {
        $this->assertModule();

        if ($lines === []) {
            throw new InvalidArgumentException(__('Add at least one medicine.'));
        }

        $occurredOn ??= now(OperationalReportService::TIMEZONE);

        return DB::transaction(function () use (
            $user, $lines, $method, $waived, $prescription, $patientName, $patientPhone, $note, $cashTaka, $onlineTaka, $onlineMethod, $occurredOn
        ): PharmacySale {
            $allocations = $this->allocate($lines);
            $total = 0;
            foreach ($allocations as $row) {
                $total += $row['qty'] * $row['item']->sell_price_taka;
            }

            if (! $waived && $total < 1) {
                throw new InvalidArgumentException(__('Sale total must be at least ৳1, or waived.'));
            }

            $cash = null;
            $split = ['cash_taka' => null, 'online_taka' => null, 'online_method' => null];

            if (! $waived && $total > 0) {
                $cashService = app(ChamberCashService::class);
                $split = $cashService->paymentSplit($method, $total, $cashTaka, $onlineTaka, $onlineMethod);
                $cash = $cashService->recordLockedIncome(
                    $user,
                    $total,
                    ChamberCashEntry::CATEGORY_PHARMACY,
                    $method,
                    $occurredOn,
                    $note,
                    $cashTaka,
                    $onlineTaka,
                    $onlineMethod,
                );
            }

            $patient = null;
            $booking = null;
            if ($prescription) {
                $prescription->loadMissing(['patient', 'visitRecord.booking']);
                $patient = $prescription->patient;
                $booking = $prescription->visitRecord?->booking;
            }

            $sale = PharmacySale::create([
                'patient_id' => $patient?->id,
                'booking_id' => $booking?->id,
                'prescription_id' => $prescription?->id,
                'cash_entry_id' => $cash?->id,
                'patient_name' => $patient?->name ?? $patientName,
                'patient_phone' => $patient?->phone ?? $patientPhone,
                'method' => $waived ? ChamberCashEntry::METHOD_CASH : $method,
                'amount' => $waived ? 0 : $total,
                'cash_taka' => $waived ? null : $split['cash_taka'],
                'mobile_taka' => $waived ? null : $split['online_taka'],
                'mobile_method' => $waived ? null : $split['online_method'],
                'waived' => $waived,
                'recorded_by' => $user->id,
                'occurred_on' => $occurredOn->toDateString(),
                'note' => $note,
            ]);

            $stock = app(PharmacyStockService::class);

            foreach ($allocations as $row) {
                /** @var PharmacyItem $item */
                $item = $row['item'];
                /** @var PharmacyDelivery $delivery */
                $delivery = $row['delivery'];
                $qty = $row['qty'];
                $share = $delivery->company_share_taka;
                $shopCut = $qty * max(0, $item->sell_price_taka - $share);

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
        });
    }

    public function void(PharmacySale $sale, User $user): PharmacySale
    {
        $this->assertModule();

        return DB::transaction(function () use ($sale, $user): PharmacySale {
            $locked = PharmacySale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new InvalidArgumentException(__('That sale is already voided.'));
            }

            $today = now(OperationalReportService::TIMEZONE)->toDateString();
            if ($locked->occurred_on?->toDateString() !== $today) {
                throw new InvalidArgumentException(__('Only today\'s sales can be voided.'));
            }

            $stock = app(PharmacyStockService::class);

            foreach ($locked->items()->lockForUpdate()->get() as $line) {
                $delivery = PharmacyDelivery::query()->whereKey($line->pharmacy_delivery_id)->lockForUpdate()->firstOrFail();
                $item = PharmacyItem::query()->whereKey($line->pharmacy_item_id)->lockForUpdate()->firstOrFail();
                $delivery->qty_on_hand += $line->qty;
                $delivery->qty_sold -= $line->qty;
                $delivery->save();
                $stock->recordAdjustment($item, PharmacyStockAdjustment::KIND_VOID_RESTORE, $user, $delivery, __('Void sale'));
            }

            app(PharmacyDoctorCommissionService::class)->voidForSale($locked);

            if (! $locked->waived && $locked->amount > 0) {
                $refund = app(ChamberCashService::class)->recordLockedExpense(
                    $user,
                    $locked->amount,
                    ChamberCashEntry::CATEGORY_PHARMACY_REFUND,
                    $locked->method,
                    now(OperationalReportService::TIMEZONE),
                    __('Void pharmacy sale'),
                    $locked->cash_taka,
                    $locked->mobile_taka,
                    $locked->mobile_method,
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

        foreach ($prescription->items as $line) {
            $item = PharmacyItem::matchByBrand($line->medicine_name);
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
    private function allocate(array $lines): array
    {
        $allocations = [];
        $remainingOnHand = [];

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

    private function assertModule(): void
    {
        if (! tenant()?->hasPharmacy()) {
            throw new InvalidArgumentException(__('Pharmacy is not enabled for this chamber.'));
        }
    }
}
