<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\PharmacyCount;
use App\Models\PharmacyCountItem;
use App\Models\PharmacyDelivery;
use App\Models\PharmacyItem;
use App\Models\PharmacyStockAdjustment;
use App\Models\PharmacySupplierSettlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PharmacyStockService
{
    public function receive(
        PharmacyItem $item,
        User $user,
        int $qty,
        int $paidNow,
        bool $returnable,
        ?int $companyShareTaka = null,
        ?string $note = null,
        ?CarbonInterface $receivedOn = null,
        string $method = ChamberCashEntry::METHOD_CASH,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): PharmacyDelivery {
        $this->assertModule();

        if ($qty < 1) {
            throw new InvalidArgumentException(__('Receive at least 1 unit.'));
        }

        if ($paidNow < 0) {
            throw new InvalidArgumentException(__('Amount paid cannot be negative.'));
        }

        $receivedOn ??= now(OperationalReportService::TIMEZONE);

        return DB::transaction(function () use (
            $item, $user, $qty, $paidNow, $returnable, $companyShareTaka, $note, $receivedOn, $method, $cashTaka, $onlineTaka, $onlineMethod
        ): PharmacyDelivery {
            $locked = PharmacyItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $share = $companyShareTaka ?? $locked->company_share_taka;

            if ($share < 0 || $share > $locked->sell_price_taka) {
                throw new InvalidArgumentException(__('Company share cannot be more than the sell price.'));
            }

            $delivery = PharmacyDelivery::create([
                'pharmacy_item_id' => $locked->id,
                'qty_received' => $qty,
                'qty_sold' => 0,
                'qty_returned' => 0,
                'qty_on_hand' => $qty,
                'company_share_taka' => $share,
                'paid_taka' => $paidNow,
                'returnable' => $returnable,
                'note' => $note,
                'received_by' => $user->id,
                'received_on' => $receivedOn->toDateString(),
            ]);

            $this->recordAdjustment($locked, PharmacyStockAdjustment::KIND_RECEIVE, $user, $delivery, $note);

            if ($paidNow > 0) {
                $cash = app(ChamberCashService::class)->recordLockedExpense(
                    $user,
                    $paidNow,
                    ChamberCashEntry::CATEGORY_PHARMACY_PURCHASE,
                    $method,
                    $receivedOn,
                    __('Pharmacy receive — :name × :qty', ['name' => $locked->name, 'qty' => $qty]),
                    $cashTaka,
                    $onlineTaka,
                    $onlineMethod,
                );
                PharmacySupplierSettlement::create([
                    'kind' => PharmacySupplierSettlement::KIND_PURCHASE,
                    'amount' => $paidNow,
                    'cash_entry_id' => $cash->id,
                    'recorded_by' => $user->id,
                    'occurred_on' => $receivedOn->toDateString(),
                    'note' => $note,
                ]);
            }

            return $delivery->fresh();
        });
    }

    public function returnUnsold(PharmacyItem $item, User $user, int $qty, ?string $note = null): void
    {
        $this->assertModule();

        if ($qty < 1) {
            throw new InvalidArgumentException(__('Return at least 1 unit.'));
        }

        DB::transaction(function () use ($item, $user, $qty, $note): void {
            $locked = PharmacyItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $remaining = $qty;

            $deliveries = PharmacyDelivery::query()
                ->where('pharmacy_item_id', $locked->id)
                ->where('returnable', true)
                ->where('qty_on_hand', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery) {
                if ($remaining < 1) {
                    break;
                }

                $take = min($remaining, $delivery->qty_on_hand);
                $delivery->qty_on_hand -= $take;
                $delivery->qty_returned += $take;
                $delivery->save();
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException(__('Not enough returnable stock on the shelf.'));
            }

            $this->recordAdjustment($locked, PharmacyStockAdjustment::KIND_RETURN, $user, null, $note);
        });
    }

    /**
     * @param  array<int, int>  $countedByItemId  pharmacy_item_id => counted qty
     */
    public function saveCount(PharmacyCount $count, User $user, array $countedByItemId): PharmacyCount
    {
        $this->assertModule();

        return DB::transaction(function () use ($count, $user, $countedByItemId): PharmacyCount {
            $locked = PharmacyCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PharmacyCount::STATUS_IN_PROGRESS) {
                throw new InvalidArgumentException(__('That count is already saved.'));
            }

            $items = PharmacyItem::query()->lockForUpdate()->orderBy('name')->get();

            foreach ($items as $item) {
                $system = $item->qty_on_hand;
                $counted = max(0, (int) ($countedByItemId[$item->id] ?? $system));
                $diff = $counted - $system;

                PharmacyCountItem::create([
                    'pharmacy_count_id' => $locked->id,
                    'pharmacy_item_id' => $item->id,
                    'system_qty' => $system,
                    'counted_qty' => $counted,
                    'difference' => $diff,
                ]);

                if ($diff !== 0) {
                    $this->applyCountDifference($item, $user, $diff);
                }
            }

            $locked->status = PharmacyCount::STATUS_SAVED;
            $locked->saved_by = $user->id;
            $locked->saved_at = now();
            $locked->save();

            return $locked->fresh('items');
        });
    }

    public function startCount(User $user): PharmacyCount
    {
        $this->assertModule();

        return DB::transaction(function () use ($user): PharmacyCount {
            $open = PharmacyCount::query()
                ->where('status', PharmacyCount::STATUS_IN_PROGRESS)
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw new InvalidArgumentException(__('A physical count is already in progress.'));
            }

            return PharmacyCount::create([
                'status' => PharmacyCount::STATUS_IN_PROGRESS,
                'started_by' => $user->id,
            ]);
        });
    }

    public function refreshItemQty(PharmacyItem $item): void
    {
        $sum = (int) PharmacyDelivery::query()
            ->where('pharmacy_item_id', $item->id)
            ->sum('qty_on_hand');
        $item->qty_on_hand = $sum;
        $item->save();
    }

    private function applyCountDifference(PharmacyItem $item, User $user, int $diff): void
    {
        if ($diff < 0) {
            $remaining = abs($diff);
            $deliveries = PharmacyDelivery::query()
                ->where('pharmacy_item_id', $item->id)
                ->where('qty_on_hand', '>', 0)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery) {
                if ($remaining < 1) {
                    break;
                }
                $take = min($remaining, $delivery->qty_on_hand);
                $delivery->qty_on_hand -= $take;
                $delivery->save();
                $remaining -= $take;
            }
        } else {
            PharmacyDelivery::create([
                'pharmacy_item_id' => $item->id,
                'qty_received' => $diff,
                'qty_sold' => 0,
                'qty_returned' => 0,
                'qty_on_hand' => $diff,
                'company_share_taka' => $item->company_share_taka,
                'paid_taka' => 0,
                'returnable' => true,
                'note' => __('Physical count surplus'),
                'received_by' => $user->id,
                'received_on' => now(OperationalReportService::TIMEZONE)->toDateString(),
            ]);
        }

        $this->recordAdjustment($item, PharmacyStockAdjustment::KIND_COUNT_APPLY, $user, null, __('Physical count'));
    }

    public function recordAdjustment(
        PharmacyItem $item,
        string $kind,
        User $user,
        ?PharmacyDelivery $delivery,
        ?string $note,
    ): void {
        $before = $item->qty_on_hand;
        $this->refreshItemQty($item);
        $item->refresh();

        PharmacyStockAdjustment::create([
            'pharmacy_item_id' => $item->id,
            'pharmacy_delivery_id' => $delivery?->id,
            'kind' => $kind,
            'qty_delta' => $item->qty_on_hand - $before,
            'qty_before' => $before,
            'qty_after' => $item->qty_on_hand,
            'note' => $note,
            'recorded_by' => $user->id,
        ]);
    }

    private function assertModule(): void
    {
        if (! tenant()?->hasPharmacy()) {
            throw new InvalidArgumentException(__('Pharmacy is not enabled for this chamber.'));
        }
    }
}
