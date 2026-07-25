<?php

namespace App\Filament\TenantAdmin\Resources\SlotBlocks\Pages;

use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Booking;
use App\Models\ScheduleSession;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class CreateSlotBlock extends CreateRecord
{
    protected static string $resource = SlotBlockResource::class;

    protected function afterCreate(): void
    {
        $block = $this->record;
        
        $query = Booking::where('booking_date', $block->date)->where('status', '!=', 'cancelled');
        
        if ($block->chamber_id) {
            $query->where(function($q) use ($block) {
                $q->whereHasMorph('bookable', [ScheduleSession::class], function($sub) use($block) {
                    $sub->where('chamber_id', $block->chamber_id);
                })->orWhereHasMorph('bookable', [\App\Models\LabCollectionSlot::class], function($sub) use($block) {
                    $sub->where('chamber_id', $block->chamber_id);
                });
            });
        }
        if ($block->doctor_id) {
            $query->whereHasMorph('bookable', [ScheduleSession::class], function($q) use($block) {
                $q->where('doctor_id', $block->doctor_id);
            });
        }
        
        $conflicts = $query->get();
        
        if ($conflicts->isNotEmpty()) {
            foreach ($conflicts as $booking) {
                $booking->update(['status' => 'cancelled']);
            }
            
            $links = $conflicts->map(function($booking) {
                $msg = urlencode("Hello {$booking->patient_name}, your appointment on {$booking->booking_date} has been cancelled.");
                $url = "https://wa.me/{$booking->patient_phone}?text={$msg}";
                return "<li><a href='{$url}' target='_blank' style='text-decoration:underline;color:blue;'>Notify {$booking->patient_name}</a></li>";
            })->implode('');
            
            Notification::make()
                ->title('Bookings Cancelled')
                ->body(new HtmlString("<ul>{$links}</ul>"))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
