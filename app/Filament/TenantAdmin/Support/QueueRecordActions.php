<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Services\StationsHandoffService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

final class QueueRecordActions
{
    /**
     * @param  callable(Booking): void  $callNow
     * @param  callable(Booking): void  $reinstate
     * @return list<Action|ActionGroup>
     */
    public static function compact(callable $callNow, callable $reinstate): array
    {
        return [
            ...self::slot(DeskActionLayout::SLOT_PRIMARY, $callNow, $reinstate),
            ActionGroup::make(self::slot(DeskActionLayout::SLOT_MORE, $callNow, $reinstate))
                ->label(__('More'))
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * @param  callable(Booking): void  $callNow
     * @param  callable(Booking): void  $reinstate
     * @return list<Action>
     */
    private static function slot(string $slot, callable $callNow, callable $reinstate): array
    {
        $name = fn (string $key): string => DeskActionLayout::actionName($key, $slot);
        $shown = fn (string $key): \Closure => fn (Booking $record): bool => DeskActionLayout::shows(
            $record,
            $key,
            $slot,
            DeskActionLayout::SURFACE_QUEUE,
        );

        return [
            PatientContinuityActions::toggleSeenBeforeSoftware(
                Action::make($name(DeskActionLayout::KEY_FOLLOW_UP)),
            )->visible(fn (Booking $record): bool => $record->patient !== null
                && (auth()->user()?->canWorkDesk() || auth()->user()?->isAdmin())
                && $shown(DeskActionLayout::KEY_FOLLOW_UP)($record)),

            VisitPaperScanForm::scanAction(
                Action::make($name(DeskActionLayout::KEY_SCAN)),
            )->visible(fn (Booking $record): bool => VisitPaperScanForm::mayScan($record)
                && $shown(DeskActionLayout::KEY_SCAN)($record)),

            OutdoorVitalsAction::make(Action::make($name(DeskActionLayout::KEY_VITALS)))
                ->visible(fn (Booking $record): bool => DeskActionLayout::canRecordVitals($record)
                    && $shown(DeskActionLayout::KEY_VITALS)($record)),

            CollectFeeAction::make(Action::make($name(DeskActionLayout::KEY_COLLECT_FEE)))
                ->visible(fn (Booking $record): bool => DeskActionLayout::canCollectFee($record)
                    && $shown(DeskActionLayout::KEY_COLLECT_FEE)($record)),

            StationsHandoffForm::bookAction(
                Action::make($name(DeskActionLayout::KEY_BOOK_INTERVENTION)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendVisit($record)
                    && $shown(DeskActionLayout::KEY_BOOK_INTERVENTION)($record);
            }),

            StationsHandoffForm::sendToMskAction(
                Action::make($name(DeskActionLayout::KEY_SEND_MSK)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToMsk($record)
                    && $shown(DeskActionLayout::KEY_SEND_MSK)($record);
            }),

            StationsHandoffForm::sendToReportAction(
                Action::make($name(DeskActionLayout::KEY_SEND_REPORT)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToReport($record)
                    && $shown(DeskActionLayout::KEY_SEND_REPORT)($record);
            }),

            StationsHandoffForm::moveAction(
                Action::make($name(DeskActionLayout::KEY_MOVE_INTERVENTION)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canMove($record)
                    && $shown(DeskActionLayout::KEY_MOVE_INTERVENTION)($record);
            }),

            StationsHandoffForm::sendToCounselingAction(
                Action::make($name(DeskActionLayout::KEY_COUNSELING)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToCounseling($record)
                    && $shown(DeskActionLayout::KEY_COUNSELING)($record);
            }),

            Action::make($name(DeskActionLayout::KEY_CALL_NOW))
                ->label('Call now')
                ->icon('heroicon-m-megaphone')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(fn (Booking $record) => 'Call #'.$record->serial_number.' out of turn?')
                ->modalDescription(__('Anyone already called but not yet arrived goes back to waiting — they keep their place and get no skip strike.'))
                ->modalSubmitActionLabel('Call now')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canCallNow($record)
                    && $shown(DeskActionLayout::KEY_CALL_NOW)($record))
                ->action(fn (Booking $record) => $callNow($record)),

            Action::make($name(DeskActionLayout::KEY_REINSTATE))
                ->label('Reinstate')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canReinstate($record)
                    && $shown(DeskActionLayout::KEY_REINSTATE)($record))
                ->action(fn (Booking $record) => $reinstate($record)),

            AskReviewAction::whatsapp(Action::make($name(DeskActionLayout::KEY_REVIEW_WHATSAPP)))
                ->visible(fn (Booking $record): bool => AskReviewAction::canWhatsapp($record)
                    && $shown(DeskActionLayout::KEY_REVIEW_WHATSAPP)($record)),

            AskReviewAction::sms(Action::make($name(DeskActionLayout::KEY_REVIEW_SMS)))
                ->visible(fn (Booking $record): bool => AskReviewAction::canSms($record)
                    && $shown(DeskActionLayout::KEY_REVIEW_SMS)($record)),

            ConfirmSerialNotifyAction::whatsapp(Action::make($name(DeskActionLayout::KEY_SERIAL_WHATSAPP)))
                ->visible(fn (Booking $record): bool => ConfirmSerialNotifyAction::canWhatsapp($record)
                    && $shown(DeskActionLayout::KEY_SERIAL_WHATSAPP)($record)),

            ConfirmSerialNotifyAction::sms(Action::make($name(DeskActionLayout::KEY_SERIAL_SMS)))
                ->visible(fn (Booking $record): bool => ConfirmSerialNotifyAction::canSms($record)
                    && $shown(DeskActionLayout::KEY_SERIAL_SMS)($record)),
        ];
    }
}
