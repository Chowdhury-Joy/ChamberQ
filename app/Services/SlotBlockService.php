<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vacation mode / holiday closures.
 *
 * Blocking a date that already has bookings must never silently orphan them:
 * the patients still turn up. Cancelling and producing a notification list is
 * the whole point of the feature.
 */
class SlotBlockService
{
    /**
     * Bookings that would be invalidated by this block.
     *
     * @return Builder<Booking>
     */
    public function affectedBookingsQuery(SlotBlock $block): Builder
    {
        $query = Booking::query()
            ->where('booking_date', $block->date->toDateString())
            ->whereNotIn('status', ['cancelled', 'completed']);

        // A block with neither chamber nor doctor closes the whole tenant.
        if (blank($block->chamber_id) && blank($block->doctor_id)) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($block) {
            if (filled($block->doctor_id)) {
                // Doctor-level: only their sessions are affected.
                $outer->whereHasMorph(
                    'bookable',
                    [ScheduleSession::class],
                    fn (Builder $q) => $q->where('doctor_id', $block->doctor_id)
                );

                return;
            }

            // Chamber-level: everything happening at that chamber, doctor
            // sessions and lab collection alike.
            $outer->whereHasMorph(
                'bookable',
                [ScheduleSession::class, LabCollectionSlot::class],
                fn (Builder $q) => $q->where('chamber_id', $block->chamber_id)
            );
        });
    }

    public function affectedCount(SlotBlock $block): int
    {
        return $this->affectedBookingsQuery($block)->count();
    }

    /**
     * Cancel everything the block invalidates and return it for notification.
     *
     * @return Collection<int, Booking>
     */
    public function cancelAffected(SlotBlock $block): Collection
    {
        return DB::transaction(function () use ($block) {
            $bookings = $this->affectedBookingsQuery($block)->lockForUpdate()->get();

            foreach ($bookings as $booking) {
                $booking->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $block->reason ?: __('The clinic is closed on this date.'),
                    'slot_block_id' => $block->id,
                ])->save();
            }

            return $bookings;
        });
    }
}
