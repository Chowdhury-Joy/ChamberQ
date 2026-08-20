<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Prescription;

final class VisitShareCopy
{
    public static function reviewUrl(Booking $booking): ?string
    {
        return Chamber::reviewUrlForBooking($booking);
    }

    public static function whatsappMessage(Booking $booking, ?Prescription $prescription = null): string
    {
        $reviewUrl = self::reviewUrl($booking);

        if ($prescription) {
            $message = __('Hello :name, here is your prescription from :date. You can view or print it here: :link', [
                'name' => $booking->patient_name,
                'date' => $booking->booking_date?->translatedFormat('j F Y'),
                'link' => $prescription->shareUrl(),
            ]);

            if ($reviewUrl !== null) {
                $message .= ' '.__('If you have a moment, please leave a Google review: :review', [
                    'review' => $reviewUrl,
                ]);
            }

            return $message;
        }

        return __('Hello :name, thank you for visiting. If you have a moment, please leave a Google review: :review', [
            'name' => $booking->patient_name,
            'review' => $reviewUrl ?? '',
        ]);
    }
}
