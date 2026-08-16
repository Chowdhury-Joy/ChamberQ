<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\FeeCatalogItem;
use App\Models\ReferralCommission;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReferralCommissionService
{
    public const DEFAULT_VISIT_TAKA = 200;

    public const DEFAULT_INTERVENTION_TAKA = 1000;

    public function visitCommissionTaka(?Tenant $tenant = null): int
    {
        return $this->rateFromTenant($tenant, 'referral_visit_commission_taka', self::DEFAULT_VISIT_TAKA);
    }

    public function interventionCommissionTaka(?Tenant $tenant = null): int
    {
        return $this->rateFromTenant($tenant, 'referral_intervention_commission_taka', self::DEFAULT_INTERVENTION_TAKA);
    }

    /**
     * Create or update the commission row when a patient fee is collected.
     */
    public function syncForBookingIncome(
        Booking $booking,
        ChamberCashEntry $incomeEntry,
        FeeCatalogItem $catalogItem,
    ): ?ReferralCommission {
        if (! tenant()?->hasReferrals()) {
            return null;
        }

        $booking->loadMissing('referringDoctor', 'bookable');

        if (! $booking->referring_doctor_id) {
            $this->voidForBooking($booking);

            return null;
        }

        $kind = $this->resolveKind($booking, $catalogItem);

        if ($kind === null || $incomeEntry->isWaived() || $incomeEntry->amount < 1) {
            $this->voidForBooking($booking);

            return null;
        }

        $amount = $this->amountForKind($kind, tenant());

        $commission = ReferralCommission::query()->firstOrNew([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
        ]);

        if ($commission->exists && $commission->status === ReferralCommission::STATUS_PAID) {
            return $commission;
        }

        $commission->fill([
            'referring_doctor_id' => $booking->referring_doctor_id,
            'income_cash_entry_id' => $incomeEntry->id,
            'kind' => $kind,
            'amount_taka' => $amount,
            'status' => ReferralCommission::STATUS_PENDING,
            'occurred_on' => $incomeEntry->occurred_on,
        ]);
        $commission->save();

        return $commission;
    }

    public function voidForBooking(Booking $booking): void
    {
        ReferralCommission::query()
            ->where('booking_id', $booking->id)
            ->where('status', ReferralCommission::STATUS_PENDING)
            ->update(['status' => ReferralCommission::STATUS_VOID]);
    }

    /**
     * @param  Collection<int, ReferralCommission>  $commissions
     */
    public function markPaid(Collection $commissions, User $user, string $method, ?string $note = null): ChamberCashEntry
    {
        $pending = $commissions->filter(fn (ReferralCommission $row): bool => $row->isPending());

        if ($pending->isEmpty()) {
            throw new InvalidArgumentException(__('Pick at least one pending commission.'));
        }

        $total = (int) $pending->sum('amount_taka');

        if ($total < 1) {
            throw new InvalidArgumentException(__('Nothing to pay.'));
        }

        $doctorNames = $pending
            ->each(fn (ReferralCommission $row) => $row->loadMissing('referringDoctor'))
            ->map(fn (ReferralCommission $row): string => $row->referringDoctor?->name ?? __('Doctor'))
            ->unique()
            ->values()
            ->all();

        $payoutNote = __('Referral payout: :doctors', [
            'doctors' => implode(', ', $doctorNames),
        ]);

        if (filled($note)) {
            $payoutNote .= ' — '.$note;
        }

        $expense = ChamberCashEntry::create([
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'amount' => $total,
            'category' => ChamberCashEntry::CATEGORY_REFERRAL_PAYOUT,
            'method' => $method,
            'recorded_by' => $user->id,
            'occurred_on' => now(OperationalReportService::TIMEZONE)->toDateString(),
            'note' => $payoutNote,
        ]);

        $now = now();

        foreach ($pending as $commission) {
            $commission->update([
                'status' => ReferralCommission::STATUS_PAID,
                'paid_at' => $now,
                'paid_by' => $user->id,
                'payout_cash_entry_id' => $expense->id,
            ]);
        }

        return $expense;
    }

    /**
     * @return array{pending_taka: int, paid_taka: int, patient_count: int}
     */
    public function doctorStatementSummary(ReferringDoctor $doctor, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $query = ReferralCommission::query()
            ->where('referring_doctor_id', $doctor->id)
            ->where('status', '!=', ReferralCommission::STATUS_VOID);

        if ($from) {
            $query->whereDate('occurred_on', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('occurred_on', '<=', $to->toDateString());
        }

        $rows = $query->get();

        return [
            'pending_taka' => (int) $rows->where('status', ReferralCommission::STATUS_PENDING)->sum('amount_taka'),
            'paid_taka' => (int) $rows->where('status', ReferralCommission::STATUS_PAID)->sum('amount_taka'),
            'patient_count' => $rows->count(),
        ];
    }

    private function resolveKind(Booking $booking, FeeCatalogItem $catalogItem): ?string
    {
        $kind = $catalogItem->sitting_kind;

        if (! filled($kind) && $booking->bookable_type === ScheduleSession::class) {
            $kind = $booking->bookable?->kind;
        }

        return match ($kind) {
            ScheduleSession::KIND_VISIT => ReferralCommission::KIND_VISIT,
            ScheduleSession::KIND_INTERVENTION => ReferralCommission::KIND_INTERVENTION,
            default => null,
        };
    }

    private function amountForKind(string $kind, ?Tenant $tenant): int
    {
        return match ($kind) {
            ReferralCommission::KIND_VISIT => $this->visitCommissionTaka($tenant),
            ReferralCommission::KIND_INTERVENTION => $this->interventionCommissionTaka($tenant),
            default => 0,
        };
    }

    private function rateFromTenant(?Tenant $tenant, string $key, int $default): int
    {
        $flags = $tenant?->feature_flags ?? [];
        $value = $flags[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }
}
