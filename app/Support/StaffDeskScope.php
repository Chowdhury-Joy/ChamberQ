<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Which branches and doctors a desk login may see. Owners and ChamberQ helpers
 * always see the whole clinic. Staff and doctors may be stamped with selected
 * chambers (empty pivot = all) and staff may be stamped to one doctor.
 */
final class StaffDeskScope
{
    public static function applies(User $user): bool
    {
        return $user->isStaff() || $user->isDoctor();
    }

    public static function seesAllChambers(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! self::applies($user)) {
            return true;
        }

        return $user->chambers()->count() === 0;
    }

    /**
     * @return list<int>|null null = all chambers in this tenant
     */
    public static function chamberIdsFor(User $user): ?array
    {
        if (self::seesAllChambers($user)) {
            return null;
        }

        /** @var list<int> $ids */
        $ids = $user->chambers()->pluck('chambers.id')->all();

        return $ids === [] ? null : $ids;
    }

    /**
     * @return list<int>|null null = hospital team (every doctor at allowed branches)
     */
    public static function doctorIdsFor(User $user): ?array
    {
        if (! $user->isStaff() || $user->assigned_doctor_id === null) {
            return null;
        }

        return [(int) $user->assigned_doctor_id];
    }

    public static function constrainChambers(Builder $query, User $user): Builder
    {
        $ids = self::chamberIdsFor($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    public static function constrainScheduleSessions(Builder $query, User $user): Builder
    {
        $chamberIds = self::chamberIdsFor($user);
        $doctorIds = self::doctorIdsFor($user);

        if ($chamberIds !== null) {
            $query->whereIn('chamber_id', $chamberIds);
        }

        if ($doctorIds !== null) {
            $query->whereIn('doctor_id', $doctorIds);
        }

        return $query;
    }

    public static function constrainBookings(Builder $query, User $user): Builder
    {
        $chamberIds = self::chamberIdsFor($user);
        $doctorIds = self::doctorIdsFor($user);

        if ($chamberIds === null && $doctorIds === null) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($chamberIds, $doctorIds): void {
            $outer->whereHasMorph('bookable', [ScheduleSession::class], function (Builder $session) use ($chamberIds, $doctorIds): void {
                if ($chamberIds !== null) {
                    $session->whereIn('chamber_id', $chamberIds);
                }
                if ($doctorIds !== null) {
                    $session->whereIn('doctor_id', $doctorIds);
                }
            });

            if ($chamberIds !== null) {
                $outer->orWhereHasMorph('bookable', [LabCollectionSlot::class], function (Builder $slot) use ($chamberIds): void {
                    $slot->whereIn('chamber_id', $chamberIds);
                });
            }
        });
    }

    public static function constrainCashEntries(Builder $query, User $user): Builder
    {
        $ids = self::chamberIdsFor($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('chamber_id', $ids);
    }

    public static function constrainPharmacyItems(Builder $query, User $user): Builder
    {
        $ids = self::chamberIdsFor($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.chamber_id', $ids);
    }

    public static function pharmacyItemIsVisible(User $user, \App\Models\PharmacyItem $item): bool
    {
        $ids = self::chamberIdsFor($user);

        return $ids === null || in_array((int) $item->chamber_id, $ids, true);
    }

    public static function constrainSlotBlocks(Builder $query, User $user): Builder
    {
        $chamberIds = self::chamberIdsFor($user);
        $doctorIds = self::doctorIdsFor($user);

        if ($chamberIds === null && $doctorIds === null) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($chamberIds, $doctorIds): void {
            if ($chamberIds !== null) {
                $outer->where(function (Builder $q) use ($chamberIds): void {
                    $q->whereNull('chamber_id')->orWhereIn('chamber_id', $chamberIds);
                });
            }

            if ($doctorIds !== null) {
                $outer->where(function (Builder $q) use ($doctorIds): void {
                    $q->whereNull('doctor_id')->orWhereIn('doctor_id', $doctorIds);
                });
            }
        });
    }

    public static function assertCanAccessSession(User $user, ScheduleSession $session): void
    {
        if (! self::sessionIsVisible($user, $session)) {
            throw new AccessDeniedHttpException(__('This login cannot access that chamber session.'));
        }
    }

    public static function sessionIsVisible(User $user, ScheduleSession $session): bool
    {
        $chamberIds = self::chamberIdsFor($user);
        if ($chamberIds !== null && ! in_array((int) $session->chamber_id, $chamberIds, true)) {
            return false;
        }

        $doctorIds = self::doctorIdsFor($user);
        if ($doctorIds !== null && ! in_array((int) $session->doctor_id, $doctorIds, true)) {
            return false;
        }

        return true;
    }

    public static function assertCanAccessBooking(User $user, Booking $booking): void
    {
        if (! self::bookingIsVisible($user, $booking)) {
            throw new AccessDeniedHttpException(__('This login cannot access that booking.'));
        }
    }

    public static function bookingIsVisible(User $user, Booking $booking): bool
    {
        $bookable = $booking->bookable;
        if ($bookable instanceof ScheduleSession) {
            return self::sessionIsVisible($user, $bookable);
        }

        if ($bookable instanceof LabCollectionSlot) {
            $chamberIds = self::chamberIdsFor($user);

            return $chamberIds === null || in_array((int) $bookable->chamber_id, $chamberIds, true);
        }

        $chamberIds = self::chamberIdsFor($user);
        $doctorIds = self::doctorIdsFor($user);

        return $chamberIds === null && $doctorIds === null;
    }

    /**
     * @return array<int, string>
     */
    public static function chamberOptionsFor(User $user): array
    {
        $query = Chamber::query()->orderBy('name');

        return self::constrainChambers($query, $user)->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function doctorOptionsFor(User $user): array
    {
        $query = \App\Models\Doctor::query()->orderBy('name');

        $doctorIds = self::doctorIdsFor($user);
        if ($doctorIds !== null) {
            $query->whereIn('id', $doctorIds);
        }

        return $query->pluck('name', 'id')->all();
    }

    public static function tenantHasMultipleChambers(): bool
    {
        return Chamber::query()->count() > 1;
    }

    /**
     * @param  list<int|string>  $chamberIds
     */
    public static function syncChambers(User $user, array $chamberIds): void
    {
        if ($user->isAdmin()) {
            $user->chambers()->detach();

            return;
        }

        if (! self::applies($user)) {
            $user->chambers()->detach();

            return;
        }

        $chamberIds = array_values(array_filter(array_map('intval', $chamberIds)));
        if ($chamberIds === []) {
            $user->chambers()->detach();

            return;
        }

        $validIds = Chamber::query()->whereIn('id', $chamberIds)->pluck('id')->all();
        $user->chambers()->sync(
            collect($validIds)->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $user->tenant_id]])->all()
        );
    }

    public static function guardAssignedDoctor(User $user, ?int $doctorId): void
    {
        if ($doctorId === null) {
            return;
        }

        if (! $user->isStaff()) {
            return;
        }

        if (! \App\Models\Doctor::query()->whereKey($doctorId)->exists()) {
            throw new AccessDeniedHttpException(__('Pick a doctor from this clinic.'));
        }
    }

    public static function leadMayManageStaff(User $lead, User $target): bool
    {
        if (! $target->isStaff()) {
            return false;
        }

        if ($lead->canManageUsers()) {
            return true;
        }

        if (! StaffDeskJobs::isLeadDesk($lead)) {
            return false;
        }

        $leadChambers = self::chamberIdsFor($lead);
        if ($leadChambers === null) {
            return true;
        }

        $targetChambers = $target->chambers()->pluck('chambers.id')->all();
        if ($targetChambers === []) {
            return false;
        }

        return count(array_intersect($leadChambers, $targetChambers)) > 0;
    }

    /**
     * Branch-locked leads must stamp at least one of their branches on new hires.
     *
     * @param  list<int|string>  $chamberIds
     */
    public static function assertLeadHireChamberIds(User $lead, array $chamberIds): void
    {
        if ($lead->canManageUsers() || ! StaffDeskJobs::isLeadDesk($lead)) {
            return;
        }

        $leadChambers = self::chamberIdsFor($lead);
        if ($leadChambers === null) {
            return;
        }

        $chamberIds = array_values(array_filter(array_map('intval', $chamberIds)));

        if ($chamberIds === []) {
            throw ValidationException::withMessages([
                'chamber_ids' => __('Pick at least one of your branches.'),
            ]);
        }
    }

    /**
     * @param  list<int|string>  $chamberIds
     * @return list<int>
     */
    public static function constrainChamberIdsForLeadHire(User $lead, array $chamberIds): array
    {
        $chamberIds = array_values(array_filter(array_map('intval', $chamberIds)));
        $leadChambers = self::chamberIdsFor($lead);

        if ($leadChambers === null) {
            return $chamberIds;
        }

        if ($chamberIds === []) {
            return $leadChambers;
        }

        $result = array_values(array_intersect($chamberIds, $leadChambers));

        if ($result === []) {
            throw ValidationException::withMessages([
                'chamber_ids' => __('Pick at least one branch you work at.'),
            ]);
        }

        return $result;
    }
}
