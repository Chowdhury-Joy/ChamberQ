<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSlotBlock extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = SlotBlockResource::class;

    /**
     * Cancelling belongs to `SlotBlock::booted()` → `SlotBlockService`, which
     * every path shares (admin, console, a future API). This page only reports
     * what that did; it must never run a second cancellation query of its own,
     * because the two filters drift — the old one here also swept up
     * already-completed visits and flipped them to cancelled.
     *
     * Patient names are not rendered here either: the escaped "Notify patients"
     * modal on the list is the one place that shows them, with working
     * `wa.me` links from `Booking::whatsappLink()`.
     */
    protected function afterCreate(): void
    {
        $cancelled = $this->record->cancelledBookings()->count();

        if ($cancelled === 0) {
            return;
        }

        Notification::make()
            ->title(trans_choice(
                '{1} 1 booking was cancelled|[2,*] :count bookings were cancelled',
                $cancelled,
                ['count' => $cancelled],
            ))
            ->body(__('Open “Notify patients” on this block to message each patient on WhatsApp.'))
            ->warning()
            ->persistent()
            ->send();
    }
}
