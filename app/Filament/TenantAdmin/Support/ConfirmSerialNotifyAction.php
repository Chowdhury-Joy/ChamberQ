<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsService;
use App\Support\BookingConfirmationCopy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Staff-tapped serial confirmation (Push SMS / Push WhatsApp) on Daily Roster
 * and Live Queue — covers online bookings and walk-ins that have no Book serial modal.
 */
final class ConfirmSerialNotifyAction
{
    public static function whatsapp(Action $action): Action
    {
        return $action
            ->label(__('Push WhatsApp'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->url(fn (Booking $record): string => $record->whatsappLink(self::message($record)))
            ->openUrlInNewTab()
            ->visible(fn (Booking $record): bool => self::canWhatsapp($record));
    }

    public static function sms(Action $action): Action
    {
        return $action
            ->label(__('Push SMS'))
            ->icon('heroicon-o-device-phone-mobile')
            ->color('warning')
            ->action(function (Booking $record): void {
                $message = app(SmsService::class)->sendBookingConfirmation($record, staffTap: true);
                $status = $message?->status;

                if ($status === SmsMessage::STATUS_SENT) {
                    Notification::make()->title(__('Confirmation SMS sent'))->success()->send();

                    return;
                }

                $error = match ($status) {
                    SmsMessage::STATUS_SKIPPED_NO_BALANCE => __('No SMS credits left'),
                    SmsMessage::STATUS_SKIPPED_PREF_OFF => __('SMS is off for this doctor'),
                    SmsMessage::STATUS_SKIPPED_DISABLED => __('SMS is disabled'),
                    default => __('Could not send SMS'),
                };

                Notification::make()->title($error)->danger()->send();
            })
            ->visible(fn (Booking $record): bool => self::canSms($record));
    }

    public static function canWhatsapp(Booking $record): bool
    {
        if (! self::canOffer($record)) {
            return false;
        }

        return Doctor::resolveForBooking($record)?->wantsWhatsapp(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false;
    }

    public static function canSms(Booking $record): bool
    {
        if (! self::canOffer($record)) {
            return false;
        }

        return Doctor::resolveForBooking($record)?->wantsPushSms(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false;
    }

    public static function canOffer(Booking $record): bool
    {
        return ! in_array($record->status, ['cancelled', 'no_show'], true)
            && filled($record->patient_phone)
            && self::actorMayNotify();
    }

    public static function message(Booking $record): string
    {
        return BookingConfirmationCopy::whatsappMessage($record);
    }

    private static function actorMayNotify(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->belongsToCurrentTenant()
            && (
                $user->canManageOps()
                || $user->canManageQueue()
                || $user->canWorkDesk()
            );
    }
}
