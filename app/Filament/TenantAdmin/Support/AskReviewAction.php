<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsService;
use App\Support\VisitShareCopy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class AskReviewAction
{
    public static function whatsapp(Action $action): Action
    {
        return $action
            ->label(__('Send review via WhatsApp'))
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->url(fn (Booking $record): string => $record->whatsappLink(
                VisitShareCopy::whatsappMessage($record),
            ))
            ->openUrlInNewTab()
            ->visible(fn (Booking $record): bool => self::canWhatsapp($record));
    }

    public static function sms(Action $action): Action
    {
        return $action
            ->label(__('Send review SMS'))
            ->icon('heroicon-o-device-phone-mobile')
            ->color('warning')
            ->action(function (Booking $record): void {
                try {
                    $message = app(SmsService::class)->sendReviewNotice($record);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $status = $message?->status;

                if ($status === SmsMessage::STATUS_SENT) {
                    Notification::make()->title(__('Review SMS sent'))->success()->send();

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

    public static function canOffer(Booking $record): bool
    {
        return $record->status === 'completed'
            && filled($record->patient_phone)
            && VisitShareCopy::reviewUrl($record) !== null
            && self::actorMayNotify();
    }

    public static function canWhatsapp(Booking $record): bool
    {
        if (! self::canOffer($record)) {
            return false;
        }

        $doctor = Doctor::resolveForBooking($record);

        return $doctor?->wantsWhatsapp(Doctor::NOTIFY_PRESCRIPTION) ?? true;
    }

    public static function canSms(Booking $record): bool
    {
        if (! self::canOffer($record)) {
            return false;
        }

        $doctor = Doctor::resolveForBooking($record);

        return $doctor?->wantsSms(Doctor::NOTIFY_PRESCRIPTION) ?? false;
    }

    private static function actorMayNotify(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->belongsToCurrentTenant()
            && (
                $user->canManageOps()
                || $user->canManageQueue()
                || $user->canViewVisitNotes()
                || $user->canWorkDesk()
            );
    }
}
